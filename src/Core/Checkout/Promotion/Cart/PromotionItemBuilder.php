<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;

class PromotionItemBuilder
{
    /**
     * will be used as prefix for the key
     * within placeholder items
     */
    public const PLACEHOLDER_PREFIX = 'promotion-';

    /**
     * Builds a new placeholder promotion line item that does not have
     * any side effects for the calculation. It will contain the code
     * within the payload which can then be used to create a real promotion item.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function buildPlaceholderItem(string $code): LineItem
    {
        // void duplicate codes with other items
        // that might not be from the promotion scope
        $uniqueKey = self::PLACEHOLDER_PREFIX . $code;

        $item = new LineItem($uniqueKey, PromotionProcessor::LINE_ITEM_TYPE);
        $item->setLabel($uniqueKey);
        $item->setGood(false);

        // this is used to pass on the code for later usage
        $item->setReferencedId($code);

        // this is important to avoid any side effects when calculating the cart
        // a percentage of 0,00 will just do nothing
        $item->setPriceDefinition(new PercentagePriceDefinition(0));

        return $item;
    }

    /**
     * Builds a new Line Item for the provided discount and its promotion.
     * It will automatically reference all provided "product" item Ids within the payload.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function buildDiscountLineItem(string $code, PromotionEntity $promotion, PromotionDiscountEntity $discount, string $currencyId, float $currencyFactor = 1.0): LineItem
    {
        //get the rules collection of discount
        $discountRuleCollection = $discount->getDiscountRules();

        // this is our target Filter that may be null if discount has no filters
        $targetFilter = null;

        // we do only need to build a target rule if user has allowed it
        // and the rule collection is not empty
        if ($discountRuleCollection instanceof RuleCollection && $discount->isConsiderAdvancedRules() && $discountRuleCollection->count() > 0) {
            $targetFilter = new OrRule();

            foreach ($discountRuleCollection as $discountRule) {
                /** @var Rule|string|null $rule */
                $rule = $discountRule->getPayload();

                if ($rule instanceof Rule) {
                    $targetFilter->addRule($rule);
                }
            }
        }

        // our promotion values are always negative values.
        // either type percentage or absolute needs to be negative to get
        // automatically subtracted within the calculation process
        $promotionValue = -abs($discount->getValue());

        switch ($discount->getType()) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                $promotionValue = -$this->getCurrencySpecificValue($discount, $discount->getValue(), $currencyId, $currencyFactor);
                $promotionDefinition = new AbsolutePriceDefinition($promotionValue, $targetFilter);

                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                $promotionDefinition = new PercentagePriceDefinition($promotionValue, $targetFilter);

                break;

            case PromotionDiscountEntity::TYPE_FIXED:
            case PromotionDiscountEntity::TYPE_FIXED_UNIT:
                $promotionValue = -abs($this->getCurrencySpecificValue($discount, $discount->getValue(), $currencyId, $currencyFactor));
                $promotionDefinition = new AbsolutePriceDefinition($promotionValue, $targetFilter);

                break;

            default:
                $promotionDefinition = null;
        }

        if ($promotionDefinition === null) {
            throw new UnknownPromotionDiscountTypeException($discount);
        }

        // build our discount line item
        // and make sure it has everything as dynamic content.
        // this is necessary for the recalculation process.
        $promotionItem = new LineItem($discount->getId(), PromotionProcessor::LINE_ITEM_TYPE);
        $promotionItem->setLabel($promotion->getTranslation('name'));
        $promotionItem->setDescription($promotion->getTranslation('name'));
        $promotionItem->setGood(false);
        $promotionItem->setRemovable(true);
        $promotionItem->setPriceDefinition($promotionDefinition);

        // always make sure we have a valid code entry.
        // this helps us to identify the item by code later on.
        // we use the one from the argument, because that one tells us why this
        // promotion is added...it might not just be the promotion code, but
        // one of the thousand individual codes for it...thus we have an
        // external algorithm that makes our lookup why this promotion is added.
        $promotionItem->setReferencedId($code);

        // add custom content to our payload.
        // we need this as meta data information.
        $promotionItem->setPayload(
            $this->buildPayload(
                $code,
                $discount,
                $promotion,
                $currencyId,
                $currencyFactor
            )
        );

        // add our lazy-validation rules.
        // this is required within the recalculation process.
        // if the requirements are not met, the calculation process
        // will remove our discount line item.
        $promotionItem->setRequirement($promotion->getPreconditionRule());

        return $promotionItem;
    }

    /**
     * in case of a delivery discount we add a 0.0 lineItem just to show customers and
     * shop owners, that delivery costs have been discounted by a promotion discount
     * if promotion is a auto promotion (no code) it may not be removed from cart
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function buildDeliveryPlaceholderLineItem(LineItem $discount, QuantityPriceDefinition $priceDefinition, CalculatedPrice $price): LineItem
    {
        $mayRemove = true;
        if ($discount->getReferencedId() === null) {
            $mayRemove = false;
        }
        // create a fake lineItem that stores our promotion code
        $promotionItem = new LineItem($discount->getId(), PromotionProcessor::LINE_ITEM_TYPE, $discount->getReferencedId(), 1);
        $promotionItem->setLabel($discount->getLabel());
        $promotionItem->setDescription($discount->getLabel());
        $promotionItem->setGood(false);
        $promotionItem->setRemovable($mayRemove);
        $promotionItem->setPayload($discount->getPayload());
        $promotionItem->setPriceDefinition($priceDefinition);
        $promotionItem->setPrice($price);

        return $promotionItem;
    }

    /**
     * Builds a custom payload array from the provided promotion data.
     * This will make sure we have our eligible items referenced as meta data
     * and also have the code in our payload.
     */
    private function buildPayload(string $code, PromotionDiscountEntity $discount, PromotionEntity $promotion, string $currencyId, float $currencyFactor): array
    {
        $payload = [];

        // to save how many times a promotion has been used, we need to know the promotion's id during checkout
        $payload['promotionId'] = $promotion->getId();

        // set discountId
        $payload['discountId'] = $discount->getId();

        // set the discount type absolute, percentage, ...
        $payload['discountType'] = $discount->getType();

        // set the code of this discount
        $payload['code'] = $code;

        // set value of discount in payload
        $payload['value'] = (string) $discount->getValue();

        // set our max value for maximum percentage discounts
        $payload['maxValue'] = '';
        if ($discount->getType() === PromotionDiscountEntity::TYPE_PERCENTAGE && $discount->getMaxValue() !== null) {
            $payload['maxValue'] = (string) $this->getCurrencySpecificValue($discount, $discount->getMaxValue(), $currencyId, $currencyFactor);
        }

        // set the scope of the discount cart, delivery....
        $payload['discountScope'] = $discount->getScope();

        // specifies if the promotion is not combinable with any other promotion
        $payload['preventCombination'] = $promotion->isPreventCombination();

        // If all combinations are prevented the exclusions dont matter
        // otherwise sets a list of excluded promotion ids
        $payload['exclusions'] = $payload['preventCombination'] ? [] : $promotion->getExclusionIds();

        $payload['groupId'] = '';
        // if we have set a custom setgroup scope, then the group id
        // is used as suffix in the scopeKey...
        if ($discount->isScopeSetGroup()) {
            $payload['groupId'] = $discount->getSetGroupId();
            $payload['discountScope'] = PromotionDiscountEntity::SCOPE_SETGROUP;
        }

        // add all our set groups to our configuration
        // if existing. always make sure to have at least a node
        $payload['setGroups'] = [];

        if ($promotion->getSetgroups() !== null) {
            foreach ($promotion->getSetgroups() as $group) {
                $payload['setGroups'][] = [
                    'groupId' => $group->getId(),
                    'packagerKey' => $group->getPackagerKey(),
                    'value' => $group->getValue(),
                    'sorterKey' => $group->getSorterKey(),
                    'rules' => $group->getSetGroupRules(),
                ];
            }
        }

        $payload['filter'] = [
            'sorterKey' => null,
            'applierKey' => null,
            'usageKey' => null,
            'pickerKey' => null,
        ];

        if ($discount->isConsiderAdvancedRules()) {
            $payload['filter'] = [
                'sorterKey' => $discount->getSorterKey(),
                'applierKey' => $discount->getApplierKey(),
                'usageKey' => $discount->getUsageKey(),
                'pickerKey' => $discount->getPickerKey(),
            ];
        }

        return $payload;
    }

    /**
     * Gets the absolute price for the provided currency.
     * This can either be a specific value or the default discount value.
     */
    private function getCurrencySpecificValue(PromotionDiscountEntity $discount, float $default, string $currencyId, float $currencyFactor): float
    {
        $currencyPrices = $discount->getPromotionDiscountPrices();

        // if there is no special defined price return default value (=default currency)
        // multiplied by given currency factor
        if (!$currencyPrices instanceof PromotionDiscountPriceCollection || $currencyPrices->count() === 0) {
            return $default * $currencyFactor;
        }

        // there are defined special prices, let's look if we may find one in collection for sales channel currency
        // if there is one we want to return this otherwise we return standard value
        // fallback is here the default currency multiplied by given currency factor
        $discountValue = $default * $currencyFactor;

        foreach ($currencyPrices as $currencyPrice) {
            if ($currencyPrice->getCurrencyId() === $currencyId) {
                // we have found a defined price, we overwrite standard value and break loop
                $discountValue = $currencyPrice->getPrice();

                break;
            }
        }

        // return the value
        return $discountValue;
    }
}
