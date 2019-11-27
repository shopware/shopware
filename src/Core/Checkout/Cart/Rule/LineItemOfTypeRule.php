<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemOfTypeRule extends Rule
{
    /**
     * @var string
     */
    protected $lineItemType;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lineItemType = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->lineItemType = $lineItemType;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof LineItemScope) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return strcasecmp($scope->getLineItem()->getType(), $this->lineItemType) === 0;

            case self::OPERATOR_NEQ:
                return strcasecmp($scope->getLineItem()->getType(), $this->lineItemType) !== 0;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
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
}
