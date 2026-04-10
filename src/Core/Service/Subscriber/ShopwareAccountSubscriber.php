<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLoginEvent;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ShopwareAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LifecycleManager $lifecycleManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShopwareAccountLoginEvent::class => 'syncAccountRequirement',
            ShopwareAccountLogoutEvent::class => 'syncAccountRequirement',
        ];
    }

    public function syncAccountRequirement(ShopwareAccountLoginEvent|ShopwareAccountLogoutEvent $event): void
    {
        $this->lifecycleManager->syncRequirement(
            ShopwareAccountRequirement::NAME,
            $event->getContext()
        );
    }
}
