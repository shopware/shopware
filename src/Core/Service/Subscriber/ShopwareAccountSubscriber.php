<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLoginEvent;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ShopwareAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ServiceLifecycle $serviceLifecycle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShopwareAccountLoginEvent::class => 'reevaluateServices',
            ShopwareAccountLogoutEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(ShopwareAccountLoginEvent|ShopwareAccountLogoutEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
