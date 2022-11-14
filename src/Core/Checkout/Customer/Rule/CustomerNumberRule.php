<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CustomerNumberRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected $numbers;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $numbers = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->numbers = $numbers;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!\is_array($this->numbers)) {
            throw new UnsupportedValueException(\gettype($this->numbers), self::class);
        }

        return RuleComparison::stringArray($customer->getCustomerNumber(), array_map('strtolower', $this->numbers), $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'numbers' => RuleConstraints::stringArray(),
            'operator' => RuleConstraints::stringOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'customerCustomerNumber';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->taggedField('numbers');
    }
}
