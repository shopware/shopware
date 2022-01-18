<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PromotionsInCartCountRule extends Rule
{
    protected int $count;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?int $count = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->count = (int) $count;
    }

    public function getName(): string
    {
        return 'promotionsInCartCount';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $count = \count($scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE));

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return $this->count === $count;
            case self::OPERATOR_NEQ:
                return $this->count !== $count;
            case self::OPERATOR_LT:
                return $this->count > $count;
            case self::OPERATOR_GT:
                return $this->count < $count;
            case self::OPERATOR_LTE:
                return $this->count >= $count;
            case self::OPERATOR_GTE:
                return $this->count <= $count;
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'count' => [new NotBlank(), new Type('int')],
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
}
