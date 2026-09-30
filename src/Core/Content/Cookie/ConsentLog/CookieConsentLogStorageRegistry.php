<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Resolves the storage selected via `shopware.cookie_consent.log_storage` from the
 * services tagged `shopware.cookie_consent.log_storage`.
 *
 * @internal
 */
#[Package('framework')]
final class CookieConsentLogStorageRegistry
{
    /**
     * @param ServiceLocator<AbstractCookieConsentLogStorage> $storages
     */
    public function __construct(
        private readonly ServiceLocator $storages,
        private readonly string $configuredStorage,
    ) {
    }

    public function getStorage(): AbstractCookieConsentLogStorage
    {
        if (!$this->storages->has($this->configuredStorage)) {
            throw CookieException::consentLogStorageNotFound(
                $this->configuredStorage,
                array_keys($this->storages->getProvidedServices()),
            );
        }

        return $this->storages->get($this->configuredStorage);
    }
}
