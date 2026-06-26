<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system style options
 */
#[Package('framework')]
final class TypedStyleOptionValidator extends ConstraintValidator
{
    private const NUMERIC_TYPES = [StyleOptionValueType::TYPE_INTEGER, StyleOptionValueType::TYPE_NUMBER];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TypedStyleOption) {
            throw new UnexpectedTypeException($constraint, TypedStyleOption::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof StyleOptionSpecificationDto) {
            throw new UnexpectedTypeException($value, StyleOptionSpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        // Type validity is asserted separately by Assert\Choice; the cross-field rules below only
        // make sense for a known primitive, so bail otherwise to avoid duplicate violations.
        if (!\in_array($value->type, StyleOptionValueType::PRIMITIVE_TYPES, true)) {
            return;
        }

        $this->validateEnum($value, $constraint);
        $this->validateRange($value, $constraint);
        $this->validateMaxLength($value, $constraint);
        $this->validateDefault($value, $constraint);
    }

    private function validateEnum(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->enum === null) {
            return;
        }

        if (!\is_array($value->enum)) {
            $this->context->buildViolation($constraint->enumArrayMessage)
                ->atPath('enum')
                ->addViolation();

            return;
        }

        if (!array_is_list($value->enum)) {
            $this->context->buildViolation($constraint->enumListMessage)
                ->atPath('enum')
                ->addViolation();

            return;
        }

        if ($value->enum === []) {
            // An empty enum would derive a Choice with no choices, silently rejecting every value.
            $this->context->buildViolation($constraint->enumEmptyMessage)
                ->atPath('enum')
                ->addViolation();

            return;
        }

        foreach ($value->enum as $entry) {
            if ($this->matchesType($entry, $value->type)) {
                continue;
            }

            $this->context->buildViolation($constraint->enumTypeMessage)
                ->setParameter('{{ type }}', $value->type)
                ->atPath('enum')
                ->addViolation();

            return;
        }
    }

    private function validateRange(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->range === null) {
            return;
        }

        if (!\is_array($value->range)) {
            $this->context->buildViolation($constraint->rangeArrayMessage)
                ->atPath('range')
                ->addViolation();

            return;
        }

        if (!\in_array($value->type, self::NUMERIC_TYPES, true)) {
            $this->context->buildViolation($constraint->rangeTypeMessage)
                ->atPath('range')
                ->addViolation();

            return;
        }

        $min = $value->range['min'] ?? null;
        $max = $value->range['max'] ?? null;

        $minValid = $min === null || \is_int($min) || \is_float($min);
        $maxValid = $max === null || \is_int($max) || \is_float($max);

        if (!$minValid || !$maxValid || ($min === null && $max === null)) {
            $this->context->buildViolation($constraint->rangeBoundsMessage)
                ->atPath('range')
                ->addViolation();

            return;
        }

        if ($min !== null && $max !== null && $min > $max) {
            $this->context->buildViolation($constraint->rangeBoundsMessage)
                ->atPath('range')
                ->addViolation();
        }
    }

    private function validateMaxLength(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->maxLength === null) {
            return;
        }

        if (!\is_int($value->maxLength) || $value->maxLength <= 0) {
            $this->context->buildViolation($constraint->maxLengthValueMessage)
                ->atPath('maxLength')
                ->addViolation();

            return;
        }

        if ($value->type !== StyleOptionValueType::TYPE_STRING) {
            $this->context->buildViolation($constraint->maxLengthTypeMessage)
                ->atPath('maxLength')
                ->addViolation();
        }
    }

    private function validateDefault(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->default === null) {
            return;
        }

        if ($this->matchesType($value->default, $value->type)) {
            return;
        }

        $this->context->buildViolation($constraint->defaultTypeMessage)
            ->setParameter('{{ type }}', $value->type)
            ->atPath('default')
            ->addViolation();
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            StyleOptionValueType::TYPE_STRING => \is_string($value),
            StyleOptionValueType::TYPE_INTEGER => \is_int($value),
            StyleOptionValueType::TYPE_BOOLEAN => \is_bool($value),
            StyleOptionValueType::TYPE_NUMBER => \is_int($value) || \is_float($value),
            default => false,
        };
    }
}
