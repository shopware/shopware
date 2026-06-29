<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
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

    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    /**
     * Acquire the per-app lock, or throw — this never returns without the lock (unlike Symfony's
     * LockInterface::acquire(), which returns false on contention), so callers MUST release it in a finally.
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
}
