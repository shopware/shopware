<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemInProductStreamRule extends Rule
{
    /**
     * @var array<string>
     */
    protected array $streamIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string> $streamIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, array $streamIds = [])
    {
        parent::__construct();

        $this->streamIds = $streamIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemInProductStream';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfProductStream($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfProductStream($lineItem)) {
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

        $constraints['streamIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('streamIds', ProductStreamDefinition::ENTITY_NAME, true);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfProductStream(LineItem $lineItem): bool
    {
        return RuleComparison::uuids($lineItem->getPayloadValue('streamIds'), $this->streamIds, $this->operator);
    }
}
