<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @package business-ops
 */
class RuleConstraints
{
    public static function float(): array
    {
        return [new NotBlank(), new Type('numeric')];
    }

    public static function int(): array
    {
        return [new NotBlank(), new Type('int')];
    }

    public static function string(): array
    {
        return [new NotBlank(), new Type('string')];
    }

    public static function stringArray(): array
    {
        return [new NotBlank(), new ArrayOfType('string')];
    }

    public static function bool(bool $notNull = false): array
    {
        $constraint = [];

        if ($notNull) {
            $constraint[] = new NotNull();
        }

        $constraint[] = new Type('bool');

        return $constraint;
    }

    public static function uuids(): array
    {
        return [new NotBlank(), new ArrayOfUuid()];
    }

    public static function datetime(): array
    {
        return [new NotBlank(), new Type('string')];
    }

    public static function numericOperators(bool $emptyAllowed = true): array
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
        ];

        if ($emptyAllowed) {
            $operators[] = Rule::OPERATOR_EMPTY;
        }

        return [
            new NotBlank(),
            new Choice($operators),
        ];
    }

    public static function stringOperators(bool $emptyAllowed = true): array
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
        ];

        if ($emptyAllowed) {
            $operators[] = Rule::OPERATOR_EMPTY;
        }

        return [
            new NotBlank(),
            new Choice($operators),
        ];
    }

    public static function uuidOperators(bool $emptyAllowed = true): array
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
        ];

        if ($emptyAllowed) {
            $operators[] = Rule::OPERATOR_EMPTY;
        }

        return [
            new NotBlank(),
            new Choice($operators),
        ];
    }

    public static function datetimeOperators(bool $emptyAllowed = true): array
    {
        $operators = [
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
        ];

        if ($emptyAllowed) {
            $operators[] = Rule::OPERATOR_EMPTY;
        }

        return [
            new NotBlank(),
            new Choice($operators),
        ];
    }
}
