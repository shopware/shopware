<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItemFactoryHandler;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionLineItemFactory implements LineItemFactoryInterface
{
    public function supports(string $type): bool
    {
        return $type === LineItem::PROMOTION_LINE_ITEM_TYPE;
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        $uniqueKey = 'promotion-' . $data['referencedId'];
        $item = new LineItem($uniqueKey, LineItem::PROMOTION_LINE_ITEM_TYPE);
        $item->setLabel($uniqueKey);
        $item->setGood(false);

        // this is used to pass on the code for later usage
        $item->setReferencedId($data['referencedId']);

        // this is important to avoid any side effects when calculating the cart
        // a percentage of 0,00 will just do nothing
        $item->setPriceDefinition(new PercentagePriceDefinition(0, $context->getCurrency()->getDecimalPrecision()));

        return $item;
    }

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        throw new \RuntimeException(sprintf('You cannot update a line item of type "%s"', $lineItem->getType()));
    }
}
