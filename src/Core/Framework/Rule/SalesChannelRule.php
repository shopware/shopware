<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @package business-ops
 */
class SalesChannelRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected $salesChannelIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     *
     * @param array<string>|null $salesChannelIds
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

    public function getName(): string
    {
        return 'salesChannel';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('salesChannelIds', SalesChannelDefinition::ENTITY_NAME, true);
    }
}
