<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class PaymentMethodRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $paymentMethodIds;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getSalesChannelContext()->getPaymentMethod()->getId()], $this->paymentMethodIds, $this->operator);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConstraints(): array
    {
        return [
            'paymentMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'paymentMethod';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('paymentMethodIds', PaymentMethodDefinition::ENTITY_NAME, true);
    }
}
