<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
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
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityDispatchService $entityDispatchService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShopIdDeletedEvent::class => 'handleShopIdDeleted',
            ShopIdChangedEvent::class => 'handleShopIdChanged',
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

    /**
     * This event is thrown if the shopId or the appUrl of a shop has changed
     * In this case we revoke the consent and reset it afterwards, to request a new one
     */
    public function handleShopIdChanged(ShopIdChangedEvent $event): void
    {
        if ($event->oldShopId === null) {
            return;
        }

        if (
            $event->newShopId['value'] === $event->oldShopId['value']
            && $event->newShopId['app_url'] === $event->oldShopId['app_url']
        ) {
            return;
        }

        $this->resetConsent();
    }

    private function resetConsent(): void
    {
        // remove entry from system config, so it can be asked again
        $this->systemConfigService->delete(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE);
        $this->bannerService->resetIsBannerHiddenForAllUsers();
        $this->entityDispatchService->resetLastRunDateForAllEntities();
    }
}
