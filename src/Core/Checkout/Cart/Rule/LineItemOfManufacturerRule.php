<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemOfManufacturerRule extends Rule
{
    /**
     * @var array<string>
     */
    protected array $manufacturerIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string> $manufacturerIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, array $manufacturerIds = [])
    {
        parent::__construct();

        $this->manufacturerIds = $manufacturerIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemOfManufacturer';
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfManufacturers($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfManufacturers($lineItem)) {
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

        $constraints['manufacturerIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('manufacturerIds', ProductManufacturerDefinition::ENTITY_NAME, true);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfManufacturers(LineItem $lineItem): bool
    {
        $manufacturerId = (string) $lineItem->getPayloadValue('manufacturerId');
        $manufacturerArray = ($manufacturerId === '') ? [] : [$manufacturerId];

        if ($this->operator === self::OPERATOR_NEQ) {
            return !\in_array($manufacturerId, $this->manufacturerIds, true);
        }

        return RuleComparison::uuids($manufacturerArray, $this->manufacturerIds, $this->operator);
    }
}
