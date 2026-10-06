<?php

/*
 * This file is part of the florentingarnier/spam-protection-bundle package.
 *
 * (c) Florentin Garnier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FlorentinGarnier\SpamProtectionBundle\Tests\Lock;

use FlorentinGarnier\SpamProtection\SingleUseTokenRegistry;
use FlorentinGarnier\SpamProtectionBundle\Lock\SymfonyTokenLock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class SymfonyTokenLockTest extends TestCase
{
    private LockFactory $lockFactory;

    protected function setUp(): void
    {
        $this->lockFactory = new LockFactory(new FlockStore(sys_get_temp_dir()));
    }

    public function testItDeniesTheLockToAnotherRequestUntilItIsReleased(): void
    {
        $firstRequest = new SymfonyTokenLock($this->lockFactory);
        $secondRequest = new SymfonyTokenLock($this->lockFactory);

        self::assertTrue($firstRequest->acquire('token'));
        self::assertFalse($secondRequest->acquire('token'));

        $firstRequest->release('token');

        self::assertTrue($secondRequest->acquire('token'));
        $secondRequest->release('token');
    }

    public function testItLocksEachKeySeparately(): void
    {
        $lock = new SymfonyTokenLock($this->lockFactory);

        self::assertTrue($lock->acquire('first-token'));
        self::assertTrue((new SymfonyTokenLock($this->lockFactory))->acquire('second-token'));

        $lock->release('first-token');
    }

    public function testItIgnoresTheReleaseOfAKeyItDoesNotHold(): void
    {
        $lock = new SymfonyTokenLock($this->lockFactory);

        $lock->release('token');

        self::assertTrue($lock->acquire('token'));
        $lock->release('token');
    }

    /**
     * The request still consuming a token holds the lock: a simultaneous duplicate submission is rejected.
     */
    public function testItRejectsATokenSubmittedTwiceAtTheSameTime(): void
    {
        $cache = new ArrayAdapter();
        $concurrentRequest = new SymfonyTokenLock($this->lockFactory);
        $concurrentRequest->acquire('spam_protection.used_token.' . hash('sha256', 'token'));

        self::assertFalse((new SingleUseTokenRegistry($cache, new SymfonyTokenLock($this->lockFactory)))->consume('token', 60));

        $concurrentRequest->release('spam_protection.used_token.' . hash('sha256', 'token'));
        self::assertTrue((new SingleUseTokenRegistry($cache, new SymfonyTokenLock($this->lockFactory)))->consume('token', 60));
    }
}
