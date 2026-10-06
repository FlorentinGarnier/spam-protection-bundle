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

namespace FlorentinGarnier\SpamProtectionBundle\Lock;

use FlorentinGarnier\SpamProtection\TokenLock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class SymfonyTokenLock implements TokenLock
{
    /**
     * Long enough for a token check, short enough for a crashed request not to block a visitor for long.
     */
    private const TTL = 30.0;

    /** @var array<string, LockInterface> */
    private array $heldLocks = [];

    public function __construct(
        private LockFactory $lockFactory,
    ) {
    }

    public function acquire(string $key): bool
    {
        $lock = $this->lockFactory->createLock($key, self::TTL);

        if (!$lock->acquire()) {
            return false;
        }

        $this->heldLocks[$key] = $lock;

        return true;
    }

    public function release(string $key): void
    {
        if (isset($this->heldLocks[$key])) {
            $this->heldLocks[$key]->release();
            unset($this->heldLocks[$key]);
        }
    }
}
