<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Tag\TagDefinition;
use Symfony\Component\Validator\Constraint;

#[Package('business-ops')]
class LineItemTagRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemTag';

    /**
     * @internal
     *
     * @param list<string>|null $identifiers
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $identifiers = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return RuleComparison::uuids($this->extractTagIds($scope->getLineItem()), $this->identifiers, $this->operator);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if (RuleComparison::uuids($this->extractTagIds($lineItem), $this->identifiers, $this->operator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|Constraint[][]
     */
    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['identifiers'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('identifiers', TagDefinition::ENTITY_NAME, true);
    }

    /**
     * @return list<string>
     */
    private function extractTagIds(LineItem $lineItem): array
    {
        if (!$lineItem->hasPayloadValue('tagIds')) {
            return [];
        }

        return $lineItem->getPayload()['tagIds'];
    }
}
