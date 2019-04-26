<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Builder;

use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Rule\Rule;

class PromotionItemBuilder
{
    /**
     * will be used as prefix for the key
     * within placeholder items
     */
    public const PLACEHOLDER_PREFIX = 'promotion-';

    /**
     * @var string
     */
    private $lineItemType;

    public function __construct(string $lineItemType)
    {
        $this->lineItemType = $lineItemType;
    }

    /**
     * Builds a new placeholder promotion line item that does not have
     * any side effects for the calculation. It will contain the code
     * within the payload which can then be used to create a real promotion item.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function buildPlaceholderItem(string $code, int $currencyPrecision): LineItem
    {
        // void duplicate codes with other items
        // that might not be from the promotion scope
        $uniqueKey = self::PLACEHOLDER_PREFIX . $code;

        $item = new LineItem($uniqueKey, $this->lineItemType, 1);
        $item->setLabel($uniqueKey);
        $item->setGood(false);

        // this is used to pass on the code for later usage
        $item->setPayload(['code' => $code]);

        // this is important to avoid any side effects when calculating the cart
        // a percentage of 0,00 will just do nothing
        $item->setPriceDefinition(new PercentagePriceDefinition(0, $currencyPrecision));

        return $item;
    }

    /**
     * Builds a new Line Item for the provided discount and its promotion.
     * It will automatically reference all provided "product" item Ids within the payload.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function buildDiscountLineItem(PromotionEntity $promotion, PromotionDiscountEntity $discount, int $currencyPrecision, array $eligibleItemIds): LineItem
    {
        $itemFilterRule = null;

        // if (count($eligibleItemIds) > 0) {
        // if we have a valid array of items that do
        // get the discount, then create a new filter rule
        // that only uses the promotion price definition on these line items
        $itemFilterRule = new LineItemRule();
        $itemFilterRule->assign(['identifiers' => $eligibleItemIds, 'operator' => Rule::OPERATOR_EQ]);
        // }

        // our promotion values are always negative values.
        // either type percentage or absolute needs to be negative to get
        // automatically subtracted within the calculation process
        $promotionValue = -$discount->getValue();

        switch ($discount->getType()) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                $promotionDefinition = new AbsolutePriceDefinition($promotionValue, $currencyPrecision, $itemFilterRule);
                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                $promotionDefinition = new PercentagePriceDefinition($promotionValue, $currencyPrecision, $itemFilterRule);
                break;

            default:
                $promotionDefinition = null;
        }

        if ($promotionDefinition === null) {
            throw new \Exception('No Promotion Discount Type set');
        }

        // build our discount line item
        // and make sure it has everything as dynamic content.
        // this is necessary for the recalculation process.
        $promotionItem = new LineItem($discount->getId(), $this->lineItemType, 1);
        $promotionItem->setLabel($promotion->getName());
        $promotionItem->setDescription($promotion->getName());
        $promotionItem->setGood(false);
        $promotionItem->setRemovable(true);
        $promotionItem->setPriceDefinition($promotionDefinition);

        // add custom content to our payload.
        // we need this as meta data information.
        $promotionItem->setPayload($this->buildPayload($promotion, $eligibleItemIds));

        // add our lazy-validation rules.
        // this is required within the recalculation process.
        // if the requirements are not met, the calculation process
        // will remove our discount line item.
        $promotionItem->setRequirement($promotion->getPreconditionRule());

        return $promotionItem;
    }

    /**
     * Builds a custom payload array from the provided promotion data.
     * This will make sure we have our eligible items referenced as meta data
     * and also have the code in our payload.
     */
    private function buildPayload(PromotionEntity $promotion, array $eligibleItemIds): array
    {
        $payload = [];

        /** @var string $itemID */
        foreach ($eligibleItemIds as $itemID) {
            $payload['item-' . $itemID] = $itemID;
        }

        // always make sure we have a valid code entry.
        // this helps us to identify the item by code later on
        $payload['code'] = (string) $promotion->getCode();

        return $payload;
    }
}
