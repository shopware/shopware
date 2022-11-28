<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemStockRule extends Rule
{
    protected ?int $stock;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?int $stock = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->stock = $stock;
    }

    public function getName(): string
    {
        return 'cartLineItemStock';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchStock($scope->getLineItem());
        }

        if ($scope instanceof CartRuleScope) {
            return $this->matchStockFromCollection($scope->getCart()->getLineItems()->getFlat());
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::numericOperators(false),
            'stock' => RuleConstraints::int(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->intField('stock');
    }

    /**
     * @throws UnsupportedOperatorException|UnsupportedValueException
     */
    private function matchStock(LineItem $lineItem): bool
    {
        if ($this->stock === null) {
            throw new UnsupportedValueException(\gettype($this->stock), self::class);
        }

        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::numeric($deliveryInformation->getStock(), $this->stock, $this->operator);
    }

    /**
     * @param LineItem[] $lineItems
     */
    private function matchStockFromCollection(array $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            if ($this->matchStock($lineItem)) {
                return true;
            }
        }

        return false;
    }
}
