<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class PaymentMethodRule extends Rule
{
    private const NAME = 'paymentMethod';

    /**
     * @var array<string>
     */
    protected array $paymentMethodIds;

    protected string $operator;

    /**
     * @param array<string> $paymentMethodIds
     *
     * @internal
     */
    public function __construct(string $operator = RULE::OPERATOR_EQ, array $paymentMethodIds = [])
    {
        parent::__construct();

        $this->operator = $operator;
        $this->paymentMethodIds = $paymentMethodIds;
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

    public function getName(): string
    {
        return self::NAME;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('paymentMethodIds', PaymentMethodDefinition::ENTITY_NAME, true);
    }
}
