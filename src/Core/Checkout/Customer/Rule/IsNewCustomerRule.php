<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.6.0 - will be removed, use DaysSinceFirstLoginRule instead
 */
#[Package('business-ops')]
class IsNewCustomerRule extends Rule
{
    final public const RULE_NAME = 'customerIsNewCustomer';

    /**
     * @var bool
     */
    protected $isNew;

    /**
     * @internal
     */
    public function __construct(bool $isNew = true)
    {
        parent::__construct();
        $this->isNew = $isNew;
    }

    public function match(RuleScope $scope): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if (!$customer->getFirstLogin()) {
            return false;
        }

        if ($this->isNew) {
            return $customer->getFirstLogin()->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
        }

        return $customer->getFirstLogin()->format('Y-m-d') !== (new \DateTime())->format('Y-m-d');
    }

    public function getConstraints(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return [
            'isNew' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return (new RuleConfig())
            ->booleanField('isNew');
    }
}
