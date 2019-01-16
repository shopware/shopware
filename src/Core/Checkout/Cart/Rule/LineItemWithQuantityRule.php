<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
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

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof LineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. LineItemScope expected']
            );
        }

        if ($scope->getLineItem()->getKey() !== $this->id) {
            return new Match(
                false,
                ['LineItem id does not match']
            );
        }

        if ($this->quantity !== null) {
            $quantity = $scope->getLineItem()->getQuantity();

            switch ($this->operator) {
                case self::OPERATOR_GTE:
                    return new Match(
                        $quantity >= $this->quantity,
                        ['LineItem quantity too low']
                    );

                case self::OPERATOR_LTE:
                    return new Match(
                        $quantity <= $this->quantity,
                        ['LineItem quantity too high']
                    );

                case self::OPERATOR_EQ:
                    return new Match(
                        $quantity == $this->quantity,
                        ['LineItem quantity does not match']
                    );

                case self::OPERATOR_NEQ:
                    return new Match(
                        $quantity != $this->quantity,
                        ['LineItem quantity is equal']
                    );

                default:
                    throw new UnsupportedOperatorException($this->operator, __CLASS__);
            }
        }

        return new Match(true);
    }

    public function getConstraints(): array
    {
        return [
            'id' => [new NotBlank(), new Uuid()],
            'quantity' => [new NotBlank(), new Type('int')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_LTE, self::OPERATOR_GTE, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'swLineItemWithQuantity';
    }
}
