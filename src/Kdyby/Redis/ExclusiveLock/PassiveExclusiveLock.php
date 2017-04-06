<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis\ExclusiveLock;

use Kdyby;
use Nette;



/**
 * @author Jakub Trmota
 */
class PassiveExclusiveLock extends Nette\Object implements Kdyby\Redis\IExclusiveLock
{

	/**
	 * @var Kdyby\Redis\RedisClient
	 */
	private $client;

	/**
	 * @var array
	 */
	private $keys = [];

	/**
	 * Duration of the lock, this is time in seconds, how long any other process can't work with the row.
	 * Use 0 for never timeout (use this with care).
	 *
	 * @var int
	 */
	public $duration = 15;

	/**
	 * When there are too many requests trying to acquire the lock, you can set this timeout,
	 * to make them manually die in case they would be taking too long and the user would lock himself out.
	 *
	 * @var int|bool
	 */
	public $acquireTimeout = FALSE;



	/**
	 * @param Kdyby\Redis\RedisClient $redisClient
	 */
	public function __construct(Kdyby\Redis\RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}



	/**
	 * @param Kdyby\Redis\RedisClient $client
	 */
	public function setClient(Kdyby\Redis\RedisClient $client)
	{
		$this->client = $client;
	}



	/**
	 * @return int
	 */
	public function getDuration()
	{
		return $this->duration;
	}



	/**
	 * @param int $duration
	 */
	public function setDuration($duration)
	{
		$this->duration = abs((int)$duration);
	}



	/**
	 * @return int|FALSE
	 */
	public function getAcquireTimeout()
	{
		return $this->acquireTimeout;
	}



	/**
	 * @param int|FALSE $timeout
	 */
	public function setAcquireTimeout($timeout)
	{
		$this->acquireTimeout = abs((int) $timeout) ?: FALSE;
	}



	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 *
	 * @param string $key
	 * @throws Kdyby\Redis\LockException
	 * @return bool
	 */
	public function acquireLock($key)
	{
		if (isset($this->keys[$key])) {
			return $this->increaseLockTimeout($key);
		}

		$timeout = $this->acquireTimeout ? (int) $this->acquireTimeout : FALSE;
		$duration = (int) $this->duration;

		if (($timeout !== FALSE) && ($timeout <= 0)) {
			throw Kdyby\Redis\LockException::zeroTimeout();
		}

		if ($timeout && $duration && ($timeout > $duration)) {
			throw Kdyby\Redis\LockException::timeoutGreaterThanDuration();
		}

		$lockKey = $this->formatLock($key);
		$signalKey = $this->formatSignal($key);

		$busy = TRUE;
		$timedOut = FALSE;
		$maxAttempts = 10;

		while ($busy) {
			if ($maxAttempts-- == 0) {
				throw Kdyby\Redis\LockException::highConcurrency();
			}

			// generate unique rand
			do {
				$rand = Nette\Utils\Random::generate(16);
				$tryAcquireLock = $this->client->evalScript('
						if redis.call("get", KEYS[1]) == ARGV[1] then
							return -1
						end
						if tonumber(ARGV[2]) > 0 then
							return redis.call("set", KEYS[1], ARGV[1], "EX", ARGV[2], "NX")
						end
						return redis.call("set", KEYS[1], ARGV[1], "NX")
					', [$lockKey], [$rand, $this->duration]);
				if ($tryAcquireLock === -1) {
					continue;
				}
				$busy = !$tryAcquireLock;
			} while (FALSE);

			if ($busy) {
				if ($timedOut) {
					throw Kdyby\Redis\LockException::acquireTimeout();
				} else {
					$timedOut = !$this->client->blpop($signalKey, $timeout ?: $duration ?: 0) && $timeout;
				}
			}
		}

		$this->keys[$key] = $rand;

		return TRUE;
	}



	/**
	 * @param string $key
	 * @return bool
	 */
	public function release($key)
	{
		if (!isset($this->keys[$key])) {
			return FALSE;
		}

		$signalKey = self::formatSignal($key);

		$error = $this->client->evalScript('
				if redis.call("get", KEYS[1]) ~= ARGV[1] then
					return 1
				else
					redis.call("del", KEYS[2])
					redis.call("lpush", KEYS[2], 1)
					redis.call("del", KEYS[1])
					return 0
				end
			', [self::formatLock($key), $signalKey], [$this->keys[$key]]
		);
		if ($error == 1) {
			return FALSE; // Lock is not acquired or it already expired
		} else if ($error) {
			return FALSE; // Unsupported error code from release script
		}

		$this->client->del($signalKey);
		unset($this->keys[$key]);
		return TRUE;
	}



	/**
	 * @param string $key
	 * @throws Kdyby\Redis\LockException
	 * @return bool
	 */
	public function increaseLockTimeout($key)
	{
		if (!isset($this->keys[$key])) {
			return FALSE;
		}

		if (!$this->duration) {
			return FALSE;
		}

		$lockKey = self::formatLock($key);

		$error = $this->client->evalScript('
				if redis.call("get", KEYS[1]) ~= ARGV[2] then
					return 1
				elseif redis.call("ttl", KEYS[1]) < 0 then
					return 2
				else
					redis.call("expire", KEYS[1], ARGV[1])
					return 0
				end
			', [$lockKey], [$this->duration, $this->keys[$key]]
		);
		if ($error == 1) {
			throw Kdyby\Redis\LockException::durabilityTimedOut(); // Lock is not acquired or it already expired
		} else if ($error == 2) {
			throw Kdyby\Redis\LockException::noExpirationTime(); // Lock has no assigned expiration time
		} else if ($error) {
			throw Kdyby\Redis\LockException::unsupportedErrorCode($error); // Unsupported error code from increase lock timeout script
		}

		return TRUE;
	}



	/**
	 * Release all acquired locks.
	 */
	public function releaseAll()
	{
		foreach ((array)$this->keys as $key => $timeout) {
			$this->release($key);
		}
	}



	/**
	 * Updates the indexing locks timeout.
	 */
	public function increaseLocksTimeout()
	{
		foreach ($this->keys as $key => $timeout) {
			$this->increaseLockTimeout($key);
		}
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatLock($key)
	{
		return $key . ':lock';
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatSignal($key)
	{
		return $key . ':signal';
	}



	public function __destruct()
	{
		$this->releaseAll();
	}

}
