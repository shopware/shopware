<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class ShippingMethodRule extends Rule
{
    /**
     * @var array<string>
     */
    protected array $shippingMethodIds;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getSalesChannelContext()->getShippingMethod()->getId()], $this->shippingMethodIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'shippingMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'shippingMethod';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('shippingMethodIds', ShippingMethodDefinition::ENTITY_NAME, true);
    }
}
