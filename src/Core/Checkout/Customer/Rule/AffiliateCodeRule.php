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
class AffiliateCodeRule extends Rule
{
    protected string $operator;

    protected ?string $affiliateCode = null;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?string $affiliateCode = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->affiliateCode = $affiliateCode;
    }

    public function getName(): string
    {
        return 'customerAffiliateCode';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$this->affiliateCode && $this->operator !== self::OPERATOR_EMPTY) {
            throw new UnsupportedValueException(\gettype($this->affiliateCode), self::class);
        }

        if (!$affiliateCode = $customer->getAffiliateCode()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::string($affiliateCode, $this->affiliateCode ?? '', $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['affiliateCode'] = RuleConstraints::string();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->stringField('affiliateCode');
    }
}
