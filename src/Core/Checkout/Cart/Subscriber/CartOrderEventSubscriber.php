<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
readonly class CartOrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AbstractContextSwitchRoute $contextSwitchRoute
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartDeletedEvent::class => ['handleContextAddress', 1],
            CheckoutOrderPlacedEvent::class => ['handleContextAddress', 1],
        ];
    }

    public function handleContextAddress(CartDeletedEvent|CheckoutOrderPlacedEvent $event): void
    {
        $this->contextSwitchRoute->switchContext(new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]), $event->getSalesChannelContext());
    }
}
