<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class OrderTrackingCodeRule extends FlowRule
{
    public const RULE_NAME = 'orderTrackingCode';

    /**
     * @internal
     */
    public function __construct(protected bool $isSet = false)
    {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'isSet' => RuleConstraints::bool(true),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        if (!$deliveries = $scope->getOrder()->getDeliveries()) {
            return false;
        }

        $value = 0;
        foreach ($deliveries->getElements() as $delivery) {
            $value += \count(array_filter($delivery->getTrackingCodes()));
        }

        return $value > 0 === $this->isSet;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isSet');
    }
}
