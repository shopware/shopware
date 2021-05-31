<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemGoodsTotalRule extends FilterRule
{
    protected int $count;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?int $count = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->count = (int) $count;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $goods = new LineItemCollection($scope->getCart()->getLineItems()->filterGoodsFlat());
        $filter = $this->filter;
        if ($filter !== null) {
            $context = $scope->getSalesChannelContext();

            $goods = $goods->filter(static function (LineItem $lineItem) use ($filter, $context) {
                $scope = new LineItemScope($lineItem, $context);

                return $filter->match($scope);
            });
        }

        $count = $goods->getTotalQuantity();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return $count >= $this->count;

            case self::OPERATOR_LTE:
                return $count <= $this->count;

            case self::OPERATOR_GT:
                return $count > $this->count;

            case self::OPERATOR_LT:
                return $count < $this->count;

            case self::OPERATOR_EQ:
                return $count === $this->count;

            case self::OPERATOR_NEQ:
                return $count !== $this->count;

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
                new Choice([
                    self::OPERATOR_NEQ,
                    self::OPERATOR_GTE,
                    self::OPERATOR_LTE,
                    self::OPERATOR_EQ,
                    self::OPERATOR_GT,
                    self::OPERATOR_LT,
                ]),
            ],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemGoodsTotal';
    }
}
