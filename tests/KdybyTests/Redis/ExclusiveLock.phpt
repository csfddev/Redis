<?php

/**
 * Test: Kdyby\Redis\PassiveExclusiveLock.
 *
 * @testCase Kdyby\Redis\ExclusiveLockTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby;
use Kdyby\Redis\IExclusiveLock;
use Kdyby\Redis\RedisClient;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExclusiveLockTest extends AbstractRedisTestCase
{

	/**
	 * @dataProvider getExclusiveLockTypes
	 */
 	public function testLockExpired($lockType)
	{
		$client = $this->client;
		Assert::exception(function () use ($lockType, $client) {
			$first = $this->createExlusiveLock($lockType, $client);
			$first->duration = 1;

			Assert::true($first->acquireLock('foo:bar'));
			sleep(3);

			$first->increaseLockTimeout('foo:bar');

		}, 'Kdyby\Redis\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	/**
	 * @dataProvider getExclusiveLockTypes
	 */
	public function testDeadlockHandling($lockType)
	{
		$first = $this->createExlusiveLock($lockType, $this->client);
		$first->duration = 1;
		$second = $this->createExlusiveLock($lockType, new RedisClient());
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		sleep(3); // first died?

		Assert::true($second->acquireLock('foo:bar'));
	}



	public function getExclusiveLockTypes()
	{
		return [[RedisClient::EXCLUSIVELOCK_ACTIVE], [RedisClient::EXCLUSIVELOCK_PASSIVE]];
	}



	/** @return IExclusiveLock */
	private function createExlusiveLock($type, RedisClient $client)
	{
		switch ($type) {
			case RedisClient::EXCLUSIVELOCK_ACTIVE :
				return new Kdyby\Redis\ExclusiveLock\ActiveExclusiveLock($client);
				break;
			case RedisClient::EXCLUSIVELOCK_PASSIVE :
				return new Kdyby\Redis\ExclusiveLock\PassiveExclusiveLock($client);
				break;
			default :
				throw new Kdyby\Redis\RedisClientException(sprintf('Bad exclusive lock type:  %s', $type));
		}
	}

}

\run(new ExclusiveLockTest());
