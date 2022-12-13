<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Tax\TaxDefinition;

/**
 * @package business-ops
 */
class LineItemTaxationRule extends Rule
{
    /**
     * @var array<string>
     */
    protected array $taxIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string> $taxIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, array $taxIds = [])
    {
        parent::__construct();

        $this->taxIds = $taxIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemTaxation';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfTaxations($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfTaxations($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'taxIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('taxIds', TaxDefinition::ENTITY_NAME, true);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws CartException
     */
    private function matchesOneOfTaxations(LineItem $lineItem): bool
    {
        return RuleComparison::uuids([$lineItem->getPayloadValue('taxId')], $this->taxIds, $this->operator);
    }
}
