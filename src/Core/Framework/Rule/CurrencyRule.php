<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class CurrencyRule extends Rule
{
    /**
     * @var string[]
     */
    protected $currencyIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        parent::__construct();
        $this->operator = self::OPERATOR_EQ;
    }

    public function match(RuleScope $scope): Match
    {
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    \in_array($scope->getContext()->getCurrencyId(), $this->currencyIds, true),
                    ['Currency not matched']
                );
            case self::OPERATOR_NEQ:
                return new Match(
                    !\in_array($scope->getContext()->getCurrencyId(), $this->currencyIds, true),
                    ['Currency not matched']
                );
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'currencyIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }
}
