<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Validator;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemRuleValidator
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
     * Gets items that are eligible for being discounted
     * based on the discount rules within the provided promotion.
     * Every line item will be matched against these rule conditions.
     * Only IDs of items that match will be returned.
     * If none of the items are valid, then an empty array will be returned.
     *
     * @return string[]
     */
    public function getEligibleItemIds(PromotionEntity $promotion, Cart $cart, SalesChannelContext $context): array
    {
        $discountCondition = null;

        if ($promotion->getDiscountRule() instanceof RuleEntity) {
            /** @var Rule $discountCondition */
            $discountCondition = $promotion->getDiscountRule()->getPayload();
        }

        /** @var array $lineItems */
        $lineItems = $this->getNonPromotionLineItems($cart);

        $eligibleItems = [];

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            if (!$discountCondition instanceof Rule) {
                $eligibleItems[] = $lineItem->getKey();
                continue;
            }

            try {
                $itemScope = new LineItemScope($lineItem, $context);

                $matchResult = $discountCondition->match($itemScope);

                // if the rule matches, we are allowed to add a promotion for this line item
                if ($matchResult) {
                    $eligibleItems[] = $lineItem->getKey();
                }
            } catch (\Throwable $ex) {
                // todo: conditions like "total prices" might not work here, because the calculation does happen afterwards.
                continue;
            }
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
}
