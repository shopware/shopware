<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class ShippingAreaRule extends Rule
{
    /**
     * @var int[]
     */
    protected $areaIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(array $areaIds, string $operator)
    {
        $this->areaIds = $areaIds;
        $this->operator = $operator;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        $context = $scope->getCheckoutContext();
        switch ($this->operator) {
            case self::OPERATOR_EQ:

                return new Match(
                    in_array($context->getShippingLocation()->getAreaId(), $this->areaIds, true),
                    ['Shipping area id not matched']
                );
            case self::OPERATOR_NEQ:

                return new Match(
                    !in_array($context->getShippingLocation()->getAreaId(), $this->areaIds, true),
                    ['Shipping area id matched']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
