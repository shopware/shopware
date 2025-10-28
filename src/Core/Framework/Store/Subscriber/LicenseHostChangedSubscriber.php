<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'onLicenseHostChanged',
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
}
