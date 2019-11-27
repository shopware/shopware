<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class GoodsCountRule extends FilterRule
{
    /**
     * @var int
     */
    protected $count;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?int $count = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->count = $count;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $goods = $scope->getCart()->getLineItems()->filterGoods();
        if ($this->filter) {
            $context = $scope->getSalesChannelContext();

            $goods = $goods->filter(function (LineItem $lineItem) use ($context) {
                $scope = new LineItemScope($lineItem, $context);

                return $this->filter->match($scope);
            });
        }

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return $goods->count() >= $this->count;

            case self::OPERATOR_LTE:
                return $goods->count() <= $this->count;

            case self::OPERATOR_GT:
                return $goods->count() > $this->count;

            case self::OPERATOR_LT:
                return $goods->count() < $this->count;

            case self::OPERATOR_EQ:
                return $goods->count() === $this->count;

            case self::OPERATOR_NEQ:
                return $goods->count() !== $this->count;

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
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    public function getName(): string
    {
        return 'cartGoodsCount';
    }
}
