<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Commercial\Licensing\License;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class LicenseHostChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly InAppPurchaseUpdater $inAppPurchaseUpdater,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'onLicenseHostChanged',
            SystemConfigChangedEvent::class => 'updateIapKey',
            SystemConfigDomainLoadedEvent::class => 'removeIapInformationFromDomain',
        ];
    }

    public function onLicenseHostChanged(BeforeSystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== StoreRequestOptionsProvider::CONFIG_KEY_STORE_LICENSE_DOMAIN) {
            return;
        }

        $oldLicenseHost = $this->systemConfigService->get(StoreRequestOptionsProvider::CONFIG_KEY_STORE_LICENSE_DOMAIN);
        if ($oldLicenseHost === $event->getValue()) {
            // system config set was executed, but the license host did not change, so we can keep the license key
            return;
        }

        // The shop secret & IAP key is unique for each license host and thus cannot remain the same
        $this->systemConfigService->delete(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET);
        $this->systemConfigService->delete(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY);

        // Log out all users to enforce re-authentication
        $this->connection->executeStatement('UPDATE user SET store_token = NULL');
    }

    public function updateIapKey(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() === StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET && $event->getValue() !== null) {
            $this->inAppPurchaseUpdater->update(Context::createDefaultContext());
        }
    }

    /**
     * We have to remove the IAP key from the system config domain,
     * otherwise it is exposed in the admin and the admin will overwrite it automatically,
     * thus circumventing our reset logic on license host change.
     */
    public function removeIapInformationFromDomain(SystemConfigDomainLoadedEvent $event): void
    {
        if ($event->getDomain() !== 'core.store.') {
            return;
        }

        $config = $event->getConfig();
        unset($config[InAppPurchaseProvider::CONFIG_STORE_IAP_KEY]);

        $event->setConfig($config);
    }
}
