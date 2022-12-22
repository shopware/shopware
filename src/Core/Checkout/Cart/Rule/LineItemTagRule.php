<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Tag\TagDefinition;

/**
 * @package business-ops
 */
class LineItemTagRule extends Rule
{
    protected string $operator;

    /**
     * @var array<string>|null
     */
    protected ?array $identifiers;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function getName(): string
    {
        return 'cartLineItemTag';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return RuleComparison::uuids($this->extractTagIds($scope->getLineItem()), $this->identifiers, $this->operator);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if (!Feature::isActive('v6.5.0.0')) {
            $identifiers = [];

            foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
                $identifiers = array_merge($identifiers, $this->extractTagIds($lineItem));
            }

            return RuleComparison::uuids($identifiers, $this->identifiers, $this->operator);
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if (RuleComparison::uuids($this->extractTagIds($lineItem), $this->identifiers, $this->operator)) {
                return true;
            }
        }

        return false;
    }

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

    private function extractTagIds(LineItem $lineItem): array
    {
        if (!$lineItem->hasPayloadValue('tagIds')) {
            return [];
        }

        return $lineItem->getPayload()['tagIds'];
    }
}
