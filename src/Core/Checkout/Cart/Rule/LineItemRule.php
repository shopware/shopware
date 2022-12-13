<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected ?array $identifiers;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string>|null $identifiers
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>|null
     */
    public function getIdentifiers(): ?array
    {
        return $this->identifiers;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'cartLineItem';
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $parentId = $lineItem->getPayloadValue('parentId');
        if ($parentId !== null && RuleComparison::uuids([$parentId], $this->identifiers, $this->operator)) {
            return true;
        }

        $referencedId = $lineItem->getReferencedId();

        return RuleComparison::uuids([$referencedId], $this->identifiers, $this->operator);
    }
}
