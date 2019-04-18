<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemRule extends Rule
{
    /**
     * @var string[]
     */
    protected $identifiers;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof LineItemScope) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($scope->getLineItem()->getKey(), $this->identifiers, true);

            case self::OPERATOR_NEQ:
                return !\in_array($scope->getLineItem()->getKey(), $this->identifiers, true);

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItem';
    }
}
