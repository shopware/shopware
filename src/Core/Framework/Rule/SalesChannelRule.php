<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('business-ops')]
class SalesChannelRule extends Rule
{
    final public const RULE_NAME = 'salesChannel';

    /**
     * @internal
     *
     * @param list<string>|null $salesChannelIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $salesChannelIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getSalesChannelContext()->getSalesChannel()->getId()], $this->salesChannelIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'salesChannelIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('salesChannelIds', SalesChannelDefinition::ENTITY_NAME, true);
    }
}
