<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ShopIdChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BannerService $bannerService,
        private readonly EntityDispatchService $entityDispatchService,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShopIdDeletedEvent::class => 'handleShopIdDeleted',
        ];
    }

    /**
     * This event is thrown if there is still a shop outside using the old shopId.
     * In this case we must not revoke the consent but only reset it and show the banner for all users again
     */
    public function handleShopIdDeleted(ShopIdDeletedEvent $event): void
    {
        $this->resetConsent();
    }

    private function resetConsent(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            [
                'name' => BackendData::NAME,
                'identifier' => 'system',
            ]
        );
        $this->bannerService->resetIsBannerHiddenForAllUsers();
        $this->entityDispatchService->resetLastRunDateForAllEntities();
    }
}
