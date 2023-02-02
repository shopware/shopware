<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @major-deprecated (flag:FEATURE_NEXT_17016) This rule will be removed. Use the LineItemRule instead.
 */
class LineItemsInCartRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected ?array $identifiers;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function match(RuleScope $scope): bool
    {
        if (Feature::isActive('FEATURE_NEXT_17016')) {
            throw new \RuntimeException('LineItemsInCartRule is deprecated and will be removed. Use LineItemRule instead.');
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if ($this->identifiers === null) {
            return false;
        }

        $elements = $scope->getCart()->getLineItems()->getFlat();
        $identifiers = array_map(static function (LineItem $element) {
            return $element->getReferencedId() ?: null;
        }, $elements);

        return RuleComparison::uuids($identifiers, $this->identifiers, $this->operator);
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
        return 'cartLineItemsInCart';
    }
}
