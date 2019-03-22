<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Builder;

use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
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
    private $lineItemType = '';

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

        $item = new LineItem($uniqueKey, $this->lineItemType, 1, LineItem::VOUCHER_PRIORITY);
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
     * Builds a new promotion line item from the provided promotion entity.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function buildPromotionItem(PromotionEntity $promotion, int $currencyPrecision, array $eligibleItemIds): LineItem
    {
        $itemFilterRule = null;

        if (count($eligibleItemIds) > 0) {
            // if we have a valid array of items that do
            // get the discount, then create a new filter rule
            // that only uses the promotion price definition on these line items
            $itemFilterRule = new LineItemRule();
            $itemFilterRule->assign(['identifiers' => $eligibleItemIds, 'operator' => Rule::OPERATOR_EQ]);
        }

        // our promotion values are always negative values.
        // either type percentage or absolute needs to be negative to get
        // automatically substracted within the calculation process
        $promotionValue = -$promotion->getValue();

        if ($promotion->isPercental()) {
            $promotionDefinition = new PercentagePriceDefinition($promotionValue, $currencyPrecision, $itemFilterRule);
        } else {
            $promotionDefinition = new AbsolutePriceDefinition($promotionValue, $currencyPrecision, $itemFilterRule);
        }

        $promotionItem = new LineItem(
            $promotion->getId(),
            $this->lineItemType,
            1,
            LineItem::VOUCHER_PRIORITY
        );

        $promotionItem->setLabel($promotion->getName());
        $promotionItem->setDescription($promotion->getName());
        $promotionItem->setGood(false);
        $promotionItem->setPriceDefinition($promotionDefinition);

        $promotionItem->setRemovable(true);

        $payload = [];

        /** @var string $itemID */
        foreach ($eligibleItemIds as $itemID) {
            $payload['item-' . $itemID] = $itemID;
        }

        // always make sure we have a valid code entry.
        // this helps us to identify the item by code later on
        $payload['code'] = (string) $promotion->getCode();

        $promotionItem->setPayload($payload);

        return $promotionItem;
    }
}
