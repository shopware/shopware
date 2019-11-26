<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CartWeightRule extends Rule
{
    /**
     * @var float
     */
    protected $weight;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $weight = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->weight = $weight;
    }

    public function getName(): string
    {
        return 'cartWeight';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $cartWeight = $this->calculateCartWeight($scope->getCart());

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return FloatComparator::equals($cartWeight, $this->weight);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($cartWeight, $this->weight);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($cartWeight, $this->weight);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($cartWeight, $this->weight);

            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($cartWeight, $this->weight);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($cartWeight, $this->weight);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'weight' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_EQ,
                        self::OPERATOR_LTE,
                        self::OPERATOR_GTE,
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    private function calculateCartWeight(Cart $cart): float
    {
        $weight = 0.0;

        /* @var LineItem $lineItem */
        foreach ($cart->getLineItems() as $lineItem) {
            $itemWeight = 0.0;
            if ($lineItem->getDeliveryInformation() !== null) {
                $itemWeight = $lineItem->getDeliveryInformation()->getWeight();
            }

            $weight += $itemWeight * $lineItem->getQuantity();
        }

        return $weight;
    }
}
