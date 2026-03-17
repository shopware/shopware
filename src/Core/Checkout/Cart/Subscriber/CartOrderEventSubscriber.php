<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Subscriber;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
readonly class CartOrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LineItemGroupBuilder $lineItemGroupBuilder
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => 'resetBuilder',
            BeforeLineItemRemovedEvent::class => 'resetBuilder',
        ];
    }

    public function resetBuilder(BeforeLineItemAddedEvent|BeforeLineItemRemovedEvent $event): void
    {
        // We must reset the calculated results when a line item is added or removed.
        $this->lineItemGroupBuilder->reset();
    }
}
