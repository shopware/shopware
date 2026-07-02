<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Per-app lock shared by registration, secret rotation and recovery so at most one of them runs for a given
 * app at a time. The lock is advisory and self-releasing (it expires after a short TTL), so a crashed process
 * cannot block an app forever; callers MUST release it in a finally block. Across machines this relies on a
 * shared lock store (LOCK_DSN) — which a clustered Shopware already needs for its scheduled tasks.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppRegistrationLock
{
    /**
     * Only has to outlive a single attempt: the app HTTP client times out after 5s, so 30s comfortably covers
     * the handshake + confirm + database writes.
     */
    private const TTL_SECONDS = 30;
    private const KEY_PREFIX = 'app-secret-rotation-';

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Run $operation while holding the per-app lock; the lock is released afterwards even when the operation
     * throws. The operation receives the lock so a slow step can {@see refresh()} it.
     *
     * @template T
     *
     * @param \Closure(LockInterface): T $operation
     *
     * @throws AppException appSecretRotationInProgress when another rotation/recovery/registration holds the
     *                      lock; appSecretLockUnavailable when the lock store itself is unreachable
     *
     * @return T
     */
    public function locked(string $appId, \Closure $operation)
    {
        $lock = $this->acquire($appId);

        try {
            return $operation($lock);
        } finally {
            $this->release($lock, $appId);
        }
    }

    /**
     * Callers MUST release the acquired lock in a finally block ({@see locked()} does both).
     *
     * @throws AppException appSecretRotationInProgress when another rotation/recovery/registration holds the
     *                      lock; appSecretLockUnavailable when the lock store itself is unreachable
     */
    public function acquire(string $appId): LockInterface
    {
        $lock = $this->lockFactory->createLock(self::KEY_PREFIX . $appId, self::TTL_SECONDS);

        try {
            if (!$lock->acquire()) {
                throw AppException::appSecretRotationInProgress($appId);
            }
        } catch (LockConflictedException) {
            throw AppException::appSecretRotationInProgress($appId);
        } catch (LockAcquiringException $e) {
            // Lock store unreachable — surface it as the typed domain exception (rationale on appSecretLockUnavailable).
            throw AppException::appSecretLockUnavailable($appId, $e);
        }

        return $lock;
    }

    /**
     * Reset the lock's TTL before a slow step (an app HTTP round trip, a long-running install). A lock that
     * could not be refreshed was lost to another process — abort loudly instead of interleaving with it.
     *
     * @throws AppException appSecretRotationInProgress when the lock was lost to another process;
     *                      appSecretLockUnavailable when the lock store itself is unreachable
     */
    public function refresh(LockInterface $lock, string $appId): void
    {
        try {
            $lock->refresh();
        } catch (LockConflictedException) {
            throw AppException::appSecretRotationInProgress($appId);
        } catch (LockAcquiringException $e) {
            throw AppException::appSecretLockUnavailable($appId, $e);
        }
    }

    /**
     * Release without ever throwing: a store failure here must not mask the operation's real outcome (the
     * lock self-expires anyway). Symfony's release() throws LockReleasingException on store failure.
     */
    public function release(LockInterface $lock, string $appId): void
    {
        try {
            $lock->release();
        } catch (LockReleasingException $e) {
            $this->logger->warning('Could not release app registration lock.', [
                'appId' => $appId,
                'exception' => $e,
            ]);
        }
    }
}
