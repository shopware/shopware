<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class PaymentMethodRule extends Rule
{
    final public const RULE_NAME = 'paymentMethod';

    /**
     * @param list<string> $paymentMethodIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = RULE::OPERATOR_EQ,
        protected array $paymentMethodIds = []
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getSalesChannelContext()->getPaymentMethod()->getId()], $this->paymentMethodIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'paymentMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('paymentMethodIds', PaymentMethodDefinition::ENTITY_NAME, true);
    }
}
