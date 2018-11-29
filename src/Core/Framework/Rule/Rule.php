<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;

abstract class Rule extends Struct
{
    public const OPERATOR_GTE = '>=';

    public const OPERATOR_LTE = '<=';

    public const OPERATOR_EQ = '=';

    public const OPERATOR_NEQ = '!=';

    /**
     * @var string
     */
    protected $type;

    public function __construct()
    {
        $this->type = static::class;
    }

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     */
    abstract public function match(RuleScope $scope): Match;

    protected function buildViolation(
        string $messageTemplate,
        array $parameters,
        $root = null,
        string $propertyPath = null,
        $invalidValue = null,
        $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            $plural = null,
            $code,
            $constraint = null,
            $cause = null
        );
    }
}
