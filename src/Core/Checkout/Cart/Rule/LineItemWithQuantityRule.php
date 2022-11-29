<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @package business-ops
 */
class LineItemWithQuantityRule extends Rule
{
    protected ?string $id;

    protected ?int $quantity;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?string $id = null, ?int $quantity = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->id = $id;
        $this->quantity = $quantity;
    }

    /**
     * @throws UnsupportedOperatorException
     */
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

    public function getConstraints(): array
    {
        return [
            'id' => [new NotBlank(), new Uuid()],
            'quantity' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemWithQuantity';
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        if ($lineItem->getReferencedId() !== $this->id && $lineItem->getPayloadValue('parentId') !== $this->id) {
            return false;
        }

        if ($this->quantity === null) {
            return true;
        }

        return RuleComparison::numeric($lineItem->getQuantity(), $this->quantity, $this->operator);
    }
}
