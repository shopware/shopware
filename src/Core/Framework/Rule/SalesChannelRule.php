<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

class SalesChannelRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected $salesChannelIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
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
}
