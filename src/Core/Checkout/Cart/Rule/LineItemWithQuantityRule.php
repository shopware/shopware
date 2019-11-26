<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemWithQuantityRule extends Rule
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $operator;

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
        if (!$scope instanceof LineItemScope) {
            return false;
        }

        if ($scope->getLineItem()->getId() !== $this->id) {
            return false;
        }

        if ($this->quantity !== null) {
            $quantity = $scope->getLineItem()->getQuantity();

            switch ($this->operator) {
                case self::OPERATOR_GTE:
                    return $quantity >= $this->quantity;

                case self::OPERATOR_LTE:
                    return $quantity <= $this->quantity;

                case self::OPERATOR_GT:
                    return $quantity > $this->quantity;

                case self::OPERATOR_LT:
                    return $quantity < $this->quantity;

                case self::OPERATOR_EQ:
                    return $quantity === $this->quantity;

                case self::OPERATOR_NEQ:
                    return $quantity !== $this->quantity;

                default:
                    throw new UnsupportedOperatorException($this->operator, self::class);
            }
        }

        return true;
    }

    public function getConstraints(): array
    {
        return [
            'id' => [new NotBlank(), new Uuid()],
            'quantity' => [new NotBlank(), new Type('int')],
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

    public function getName(): string
    {
        return 'cartLineItemWithQuantity';
    }
}
