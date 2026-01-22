<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\LockRegistry;

/**
 * Configures Symfony's cache stampede protection based on session configuration.
 *
 * When using file-based sessions, a deadlock (ABBA pattern) can occur:
 * - Process 1: Acquires Session File Lock -> Needs Cache -> Tries to acquire Cache Lock
 * - Process 2: Acquires Cache Lock (for stampede protection) -> Needs Session -> Tries to acquire Session File Lock
 *
 * This is a workaround that clears the LockRegistry files, effectively disabling file-based locking for cache stampede protection.
 * It's a trade-off between performance and deadlock avoidance.
 * This is an opt-in fix for environments where Redis (the recommended solution) is not available.
 *
 * See https://github.com/shopware/shopware/issues/12823#issuecomment-3677936635
 *
 * @internal
 *
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\Adapter\Cache\StampedeProtectionConfiguratorTest
 */
#[Package('framework')]
readonly class StampedeProtectionConfigurator
{
    public function __construct(
        private bool $disableStampedeProtection,
        /**
         * Needed for testing with different session handlers independently of php configuration.
         */
        private ?string $sessionSaveHandler = null
    ) {
    }

    /**
     * Applies stampede protection configuration based on settings and session handler.
     */
    public function apply(): void
    {
        if (!$this->disableStampedeProtection) {
            return;
        }

        if (!class_exists(LockRegistry::class)) {
            return;
        }

        if (!$this->isFileBasedSession()) {
            return;
        }

        LockRegistry::setFiles([]);
    }

    private function isFileBasedSession(): bool
    {
        $handler = $this->sessionSaveHandler ?? \ini_get('session.save_handler');

        return $handler === 'files';
    }
}
