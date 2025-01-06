<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class AdminSalesChannelSourceRule extends Rule
{
    final public const RULE_NAME = 'adminSalesChannelSource';

    /**
     * @internal
     */
    public function __construct(protected bool $hasAdminSalesChannelSource = false)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $hasAdminSalesChannelSource = $scope->getContext()->getSource() instanceof AdminSalesChannelApiSource;

        if ($this->hasAdminSalesChannelSource) {
            return $hasAdminSalesChannelSource;
        }

        return !$hasAdminSalesChannelSource;
    }

    public function getConstraints(): array
    {
        return [
            'hasAdminSalesChannelSource' => RuleConstraints::bool(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())->booleanField('hasAdminSalesChannelSource');
    }
}
