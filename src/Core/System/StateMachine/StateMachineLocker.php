<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('checkout')]
class StateMachineLocker implements ResetInterface
{
    private const LOCK_TTL = 5.0;

    /**
     * @var array<string, true>
     */
    private array $acquiredLocks = [];

    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    /**
     * @param \Closure(): StateMachineTransitionResult $closure
     */
    public function locked(Transition $transition, Context $context, \Closure $closure): StateMachineTransitionResult
    {
        $lockKey = $this->getLockKey($transition, $context);

        // If the lock is already acquired for this process, we can skip acquiring it again
        // this is a guard against deadlocks in the same process
        if (isset($this->acquiredLocks[$lockKey])) {
            return $closure();
        }

        $lock = $this->lockFactory->createLock($lockKey, self::LOCK_TTL);

        if (!$lock->acquire(true)) {
            throw StateMachineException::stateMachineTransitionLocked($transition->getEntityName(), $transition->getEntityId());
        }

        $this->acquiredLocks[$lockKey] = true;

        try {
            return $closure();
        } finally {
            unset($this->acquiredLocks[$lockKey]);
            $lock->release();
        }
    }

    public function getLockKey(Transition $transition, Context $context): string
    {
        return \sprintf(
            'state-machine-transition-%s',
            Hasher::hash(\implode('-', [
                $transition->getEntityName(),
                $transition->getEntityId(),
                $context->getVersionId(),
            ]))
        );
    }

    public function reset(): void
    {
        $this->acquiredLocks = [];
    }
}
