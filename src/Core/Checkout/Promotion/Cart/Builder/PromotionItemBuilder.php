<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Builder;

use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
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

        $item = new LineItem($uniqueKey, $this->lineItemType);
        $item->setLabel($uniqueKey);
        $item->setGood(false);

        // this is used to pass on the code for later usage
        $item->setReferencedId($code);

        // this is important to avoid any side effects when calculating the cart
        // a percentage of 0,00 will just do nothing
        $item->setPriceDefinition(new PercentagePriceDefinition(0, $currencyPrecision));

        return $item;
    }

    /**
     * Builds a new Line Item for the provided discount and its promotion.
     * It will automatically reference all provided "product" item Ids within the payload.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function buildDiscountLineItem(PromotionEntity $promotion, PromotionDiscountEntity $discount, int $currencyPrecision): LineItem
    {
        //get the rules collection of discount
        /** @var RuleCollection|null $discountRuleCollection */
        $discountRuleCollection = $discount->getDiscountRules();

        // this is our target Filter that may be null if discount has no filters
        $targetFilter = null;

        // we do only need to build a target rule if user has allowed it
        // and the rule collection is not empty
        if ($discountRuleCollection instanceof RuleCollection && $discount->isConsiderAdvancedRules() && $discountRuleCollection->count() > 0) {
            $targetFilter = new OrRule();

            /** @var RuleEntity $discountRule */
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
        $promotionValue = -$discount->getValue();

        switch ($discount->getType()) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                $promotionDefinition = new AbsolutePriceDefinition($promotionValue, $currencyPrecision, $targetFilter);
                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                $promotionDefinition = new PercentagePriceDefinition($promotionValue, $currencyPrecision, $targetFilter);
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
        $promotionItem = new LineItem($discount->getId(), $this->lineItemType);
        $promotionItem->setLabel($promotion->getName());
        $promotionItem->setDescription($promotion->getName());
        $promotionItem->setGood(false);
        $promotionItem->setRemovable(true);
        $promotionItem->setPriceDefinition($promotionDefinition);

        // always make sure we have a valid code entry.
        // this helps us to identify the item by code later on
        if ($promotion->isUseCodes()) {
            $promotionItem->setReferencedId((string) $promotion->getCode());
        }

        // add custom content to our payload.
        // we need this as meta data information.
        $promotionItem->setPayload($this->buildPayload($discount->getType(), $promotion));

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
    private function buildPayload(string $discountType, PromotionEntity $promotion): array
    {
        $payload = [];

        // to save how many times a promotion has been used, we need to know the promotion's id during checkout
        $payload['promotionId'] = $promotion->getId();

        // set the discount type absolute, percentage, ...
        $payload['discountType'] = $discountType;

        return $payload;
    }
}
