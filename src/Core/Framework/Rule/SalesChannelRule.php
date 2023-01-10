<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @package business-ops
 */
class SalesChannelRule extends Rule
{
    public const RULE_NAME = 'salesChannel';

    /**
     * @var list<string>|null
     */
    protected ?array $salesChannelIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param list<string>|null $salesChannelIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $salesChannelIds = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->salesChannelIds = $salesChannelIds;
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
