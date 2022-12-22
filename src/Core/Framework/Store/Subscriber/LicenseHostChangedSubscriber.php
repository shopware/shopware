<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package merchant-services
 *
 * @internal
 */
class LicenseHostChangedSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    private Connection $connection;

    public function __construct(
        SystemConfigService $systemConfigService,
        Connection $connection
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onLicenseHostChanged',
        ];
    }

    public function onLicenseHostChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== StoreRequestOptionsProvider::CONFIG_KEY_STORE_LICENSE_DOMAIN) {
            return;
        }

        // The shop secret is unique for each license host and thus cannot remain the same
        $this->systemConfigService->delete(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET);

        // Log out all users to enforce re-authentication
        $this->connection->executeStatement('UPDATE user SET store_token = NULL');
    }
}
