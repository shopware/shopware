<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
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

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function getName(): string
    {
        return 'cartWeight';
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CartRuleScope) {
            return new Match(false, ['Invalid Match Context. CartRuleScope expected']);
        }

        $cartWeight = $this->calculateCartWeight($scope->getCart());

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    $cartWeight === (float) $this->weight,
                    [sprintf('cartWeight is equals %f, %f wanted', $cartWeight, $this->weight)]
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    $cartWeight !== (float) $this->weight,
                    [sprintf('cartWeight is equals %f', $this->weight)]
                );
            case self::OPERATOR_GT:
                return new Match(
                    $cartWeight > (float) $this->weight,
                    [sprintf('cartWeight is smaller than %f', $this->weight)]
                );
            case self::OPERATOR_LT:
                return new Match(
                    $cartWeight < (float) $this->weight,
                    [sprintf('cartWeight is greater than %f', $this->weight)]
                );
            case self::OPERATOR_GTE:
                return new Match(
                    $cartWeight >= (float) $this->weight,
                    [sprintf('cartWeight is smaller than or equals %f', $this->weight)]
                );
            case self::OPERATOR_LTE:
                return new Match(
                    $cartWeight <= (float) $this->weight,
                    [sprintf('cartWeight is greater than or equals %f', $this->weight)]
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'weight' => [new NotBlank(), new Type('numeric')],
            'operator' => [
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
