<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class PromotionValueRule extends FilterRule
{
    protected float $amount;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = (float) $amount;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $promotions = new LineItemCollection($scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE));
        $filter = $this->filter;
        if ($filter !== null) {
            $context = $scope->getSalesChannelContext();

            $promotions = $promotions->filter(static function (LineItem $lineItem) use ($filter, $context) {
                $scope = new LineItemScope($lineItem, $context);

                return $filter->match($scope);
            });
        }

        $promotionAmount = $promotions->getPrices()->sum()->getTotalPrice() * -1;

        return RuleComparison::numeric($promotionAmount, $this->amount, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'amount' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'promotionValue';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('amount');
    }
}
