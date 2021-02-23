<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemOfTypeRule extends Rule
{
    protected string $lineItemType;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lineItemType = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->lineItemType = (string) $lineItemType;
    }

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
            'lineItemType' => [new NotBlank(), new Type('string')],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemOfType';
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return strcasecmp($lineItem->getType(), $this->lineItemType) === 0;

            case self::OPERATOR_NEQ:
                return strcasecmp($lineItem->getType(), $this->lineItemType) !== 0;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
