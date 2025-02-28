<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartSerializationCleaner
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function cleanupCart(Cart $cart): void
    {
        $customFieldAllowList = $this->connection->fetchFirstColumn('SELECT `name` FROM `custom_field` WHERE `allow_cart_expose` = 1;');

        $event = new CartBeforeSerializationEvent($cart, $customFieldAllowList);
        $this->eventDispatcher->dispatch($event);

        $this->cleanupLineItems($cart->getLineItems(), $event->getCustomFieldAllowList());

        foreach ($cart->getDeliveries() as $delivery) {
            $this->cleanupLineItems($delivery->getPositions()->getLineItems(), $event->getCustomFieldAllowList());
        }
    }

    /**
     * @param array<mixed> $customFieldAllowList
     */
    private function cleanupLineItems(LineItemCollection $lineItems, array $customFieldAllowList): void
    {
        foreach ($lineItems as $lineItem) {
            $this->cleanupLineItem($lineItem, $customFieldAllowList);
        }
    }

    /**
     * @param array<mixed> $customFieldAllowList
     */
    private function cleanupLineItem(LineItem $lineItem, array $customFieldAllowList): void
    {
        if ($lineItem->getCover()) {
            $lineItem->getCover()->setThumbnailsRo('');
        }

        $this->cleanupCustomFields($lineItem, $customFieldAllowList);

        foreach ($lineItem->getChildren() as $child) {
            $this->cleanupLineItem($child, $customFieldAllowList);
        }
    }

    /**
     * @param array<mixed> $customFieldAllowList
     */
    private function cleanupCustomFields(LineItem $lineItem, array $customFieldAllowList): void
    {
        $customFields = $lineItem->getPayloadValue('customFields');
        if (!$customFields) {
            return;
        }

        if (\count($customFieldAllowList) === 0) {
            $lineItem->setPayloadValue('customFields', []);

            return;
        }

        $lineItem->setPayloadValue(
            'customFields',
            array_intersect_key(
                $customFields,
                array_combine(
                    $customFieldAllowList,
                    $customFieldAllowList
                )
            )
        );
    }
}
