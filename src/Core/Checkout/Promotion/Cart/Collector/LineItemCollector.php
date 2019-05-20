<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Collector;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemCollector
{
    /**
     * @var string
     */
    private $promotionLineItemPlaceholder;

    public function __construct(string $promotionLineItemType)
    {
        $this->promotionLineItemPlaceholder = $promotionLineItemType;
    }

    /**
     * returns all lineItem keys that match the rules of the promotion
     */
    public function getLineItemsByDiscount(PromotionDiscountEntity $discount, Cart $cart, SalesChannelContext $context): array
    {
        /** @var array $nonPromotionLineItemsKeys */
        $nonPromotionLineItemsKeys = $this->getNonPromotionLineItemsKeys($cart);

        if (count($nonPromotionLineItemsKeys) === 0) {
            return [];
        }

        if (!$discount->isConsiderAdvancedRules()) {
            return $nonPromotionLineItemsKeys;
        }

        /** @var RuleCollection|null $discountRuleCollection */
        $discountRuleCollection = $discount->getDiscountRules();

        // discount has no rules, therefore all lineItems match discount
        if (!$discountRuleCollection instanceof RuleCollection) {
            return $nonPromotionLineItemsKeys;
        }

        /** @var array $lineItems */
        $lineItems = $this->getNonPromotionLineItems($cart);

        $matchingLineItems = [];

        // get all lineItems where rule is matching without respecting any advanced rule
        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $itemScope = new LineItemScope($lineItem, $context);
            if ($this->doesRuleCollectionMatchsLineItem($discountRuleCollection, $itemScope)) {
                $matchingLineItems[$lineItem->getId()] = $lineItem;
            }
        }

        return $matchingLineItems;
    }

    /**
     * @return array
     */
    public function getAllLineItemIDs(Cart $cart)
    {
        $eligibleItems = [];

        /** @var array $lineItems */
        $lineItems = $this->getNonPromotionLineItems($cart);

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $eligibleItems[] = $lineItem->getId();
        }

        return $eligibleItems;
    }

    /**
     * Gets all line items that are not of the provided type
     * These can be used to iterate through all "standard" items.
     */
    private function getNonPromotionLineItems(Cart $cart): array
    {
        $lineItems = array_filter(
            $cart->getLineItems()->getElements(),
            function (LineItem $lineItem) {
                return $lineItem->getType() !== $this->promotionLineItemPlaceholder;
            }
        );

        return $lineItems;
    }

    /**
     * return all LineItem Keys that are no promotions
     */
    private function getNonPromotionLineItemsKeys(Cart $cart): array
    {
        $lineItems = $this->getNonPromotionLineItems($cart);

        $lineItemKeys = [];

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $lineItemKeys[] = $lineItem->getId();
        }

        return $lineItemKeys;
    }

    /**
     * checks if a ruleCollection matches a lineItem. This function returns true if ONE rule in collection matches lineItem,
     * otherwise returns false
     */
    private function doesRuleCollectionMatchsLineItem(RuleCollection $ruleCollection, LineItemScope $lineItemScope): bool
    {
        /** @var RuleEntity $discountRule */
        foreach ($ruleCollection as $discountRule) {
            $ruleCondition = $discountRule->getPayload();

            if (!$ruleCondition instanceof Rule) {
                continue;
            }

            if ($ruleCondition->match($lineItemScope)) {
                return true;
            }
        }

        return false;
    }
}
