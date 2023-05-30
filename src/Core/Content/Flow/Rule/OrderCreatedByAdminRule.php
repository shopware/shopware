<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class OrderCreatedByAdminRule extends FlowRule
{
    final public const RULE_NAME = 'orderCreatedByAdmin';

    /**
     * @internal
     */
    public function __construct(private bool $shouldOrderBeCreatedByAdmin = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        return $this->shouldOrderBeCreatedByAdmin === (bool) $scope->getOrder()->getCreatedById();
    }

    public function getConstraints(): array
    {
        return [
            'shouldOrderBeCreatedByAdmin' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())->booleanField('shouldOrderBeCreatedByAdmin');
    }
}
