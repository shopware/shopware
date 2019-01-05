<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
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

    public function __construct()
    {
        parent::__construct();
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof LineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. LineItemScope expected']
            );
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    $scope->getLineItem()->getType() === $this->lineItemType,
                    ['LineItem type does not match']
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    $scope->getLineItem()->getType() !== $this->lineItemType,
                    ['LineItem type does match']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'lineItemType' => [new NotBlank(), new Type('string')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }
}
