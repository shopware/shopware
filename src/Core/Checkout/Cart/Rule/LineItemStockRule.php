<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemStockRule extends Rule
{
    protected ?int $stock;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?int $stock = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->stock = $stock;
    }

    public function getName(): string
    {
        return 'cartLineItemStock';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchStock($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchStock($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
            'stock' => [new NotBlank(), new Type('int')],
        ];
    }

    /**
     * @throws UnsupportedOperatorException|UnsupportedValueException
     */
    private function matchStock(LineItem $lineItem): bool
    {
        if ($this->stock === null) {
            throw new UnsupportedValueException(\gettype($this->stock), self::class);
        }

        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return false;
        }

        $stock = $deliveryInformation->getStock();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return $stock >= $this->stock;

            case self::OPERATOR_LTE:
                return $stock <= $this->stock;

            case self::OPERATOR_GT:
                return $stock > $this->stock;

            case self::OPERATOR_LT:
                return $stock < $this->stock;

            case self::OPERATOR_EQ:
                return $stock === $this->stock;

            case self::OPERATOR_NEQ:
                return $stock !== $this->stock;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
