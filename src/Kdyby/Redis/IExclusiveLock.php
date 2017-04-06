<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Kdyby;



/**
 * @author Jakub Trmota
 */
interface IExclusiveLock
{

	/**
	 * @param RedisClient $client
	 */
	public function setClient(RedisClient $client);



	/**
	 * @return int
	 */
	public function getDuration();



	/**
	 * @param int $duration
	 */
	public function setDuration($duration);



	/**
	 * @return int|FALSE
	 */
	public function getAcquireTimeout();



	/**
	 * @param int|FALSE $timeout
	 */
	public function setAcquireTimeout($timeout);



	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 *
	 * @param string $key
	 * @throws LockException
	 * @return bool
	 */
	public function acquireLock($key);



	/**
	 * @param string $key
	 * @return bool
	 */
	public function release($key);



	/**
	 * @param string $key
	 * @return bool
	 */
	public function increaseLockTimeout($key);



	/**
	 * Release all acquired locks.
	 */
	public function releaseAll();

}
