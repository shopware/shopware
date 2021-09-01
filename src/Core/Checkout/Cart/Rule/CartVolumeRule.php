<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CartVolumeRule extends Rule
{
    protected ?float $volume;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $volume = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->volume = $volume;
    }

    public function getName(): string
    {
        return 'cartVolume';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if ($this->volume === null) {
            throw new UnsupportedValueException(\gettype($this->volume), self::class);
        }

        $cartVolume = $this->calculateCartVolume($scope->getCart());

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return FloatComparator::equals($cartVolume, $this->volume);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($cartVolume, $this->volume);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($cartVolume, $this->volume);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($cartVolume, $this->volume);

            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($cartVolume, $this->volume);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($cartVolume, $this->volume);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'volume' => [new NotBlank(), new Type('numeric')],
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

    private function calculateCartVolume(Cart $cart): float
    {
        $volume = 0.0;

        foreach ($cart->getDeliveries() as $delivery) {
            if ($delivery instanceof Delivery) {
                $volume += $delivery->getPositions()->getVolume();
            }
        }

        return $volume;
    }
}
