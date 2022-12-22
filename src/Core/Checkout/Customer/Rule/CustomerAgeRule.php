<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CustomerAgeRule extends Rule
{
    protected string $operator;

    protected ?float $age = null;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $age = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->age = $age;
    }

    public function getName(): string
    {
        return 'customerAge';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$this->age && $this->operator !== self::OPERATOR_EMPTY) {
            throw new UnsupportedValueException(\gettype($this->age), self::class);
        }

        if (!$birthday = $customer->getBirthday()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $birthday = (new \DateTime())->setTimestamp($birthday->getTimestamp());
        $now = new \DateTime();

        $age = $now->diff($birthday)->y;

        return RuleComparison::numeric($age, $this->age, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['age'] = RuleConstraints::float();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->intField('age');
    }
}
