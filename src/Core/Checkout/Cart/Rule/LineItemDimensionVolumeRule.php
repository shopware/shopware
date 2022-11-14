<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemDimensionVolumeRule extends Rule
{
    protected ?float $amount;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = $amount;
    }

    public function getName(): string
    {
        return 'cartLineItemDimensionVolume';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchVolumeDimension($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchVolumeDimension($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::numericOperators(false),
            'amount' => RuleConstraints::float(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('amount', ['unit' => RuleConfig::UNIT_VOLUME]);
    }

    /**
     * @throws UnsupportedOperatorException
     */
    private function matchVolumeDimension(LineItem $lineItem): bool
    {
        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::numeric($deliveryInformation->getVolume(), $this->amount, $this->operator);
    }
}
