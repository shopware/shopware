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

        $this->validateBreakpointAware($value, $constraint);

        // Type validity is asserted separately by Assert\Choice; the cross-field rules below only
        // make sense for a known primitive, so bail otherwise to avoid duplicate violations.
        if (!\in_array($value->type, StyleOptionValueType::PRIMITIVE_TYPES, true)) {
            return;
        }

        $enumValid = $this->validateEnum($value, $constraint);
        $rangeValid = $this->validateRange($value, $constraint);
        $maxLengthValid = $this->validateMaxLength($value, $constraint);
        $this->validateDefault($value, $constraint, $enumValid, $rangeValid, $maxLengthValid);
        $this->validateAdminUI($value, $constraint);
    }

    /**
     * @return bool true when the enum facet is absent or well-formed; false when it raised a violation
     */
    private function validateEnum(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): bool
    {
        if ($value->enum === null) {
            return true;
        }

        if (!\is_array($value->enum)) {
            $this->context->buildViolation($constraint->enumArrayMessage)
                ->atPath('enum')
                ->addViolation();

            return false;
        }

        if (!array_is_list($value->enum)) {
            $this->context->buildViolation($constraint->enumListMessage)
                ->atPath('enum')
                ->addViolation();

            return false;
        }

        if ($value->enum === []) {
            // An empty enum would derive a Choice with no choices, silently rejecting every value.
            $this->context->buildViolation($constraint->enumEmptyMessage)
                ->atPath('enum')
                ->addViolation();

            return false;
        }

        foreach ($value->enum as $entry) {
            if ($this->matchesType($entry, $value->type)) {
                continue;
            }

            $this->context->buildViolation($constraint->enumTypeMessage)
                ->setParameter('{{ type }}', $value->type)
                ->atPath('enum')
                ->addViolation();

            return false;
        }

        return true;
    }

    /**
     * @return bool true when the range facet is absent or well-formed; false when it raised a violation
     */
    private function validateRange(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): bool
    {
        if ($value->range === null) {
            return true;
        }

        if (!\is_array($value->range)) {
            $this->context->buildViolation($constraint->rangeArrayMessage)
                ->atPath('range')
                ->addViolation();

            return false;
        }

        if (!\in_array($value->type, self::NUMERIC_TYPES, true)) {
            $this->context->buildViolation($constraint->rangeTypeMessage)
                ->atPath('range')
                ->addViolation();

            return false;
        }

        $min = $value->range['min'] ?? null;
        $max = $value->range['max'] ?? null;

        $minValid = $min === null || \is_int($min) || \is_float($min);
        $maxValid = $max === null || \is_int($max) || \is_float($max);

        if (!$minValid || !$maxValid || ($min === null && $max === null)) {
            $this->context->buildViolation($constraint->rangeBoundsMessage)
                ->atPath('range')
                ->addViolation();

            return false;
        }

        if ($min !== null && $max !== null && $min > $max) {
            $this->context->buildViolation($constraint->rangeBoundsMessage)
                ->atPath('range')
                ->addViolation();

            return false;
        }

        return true;
    }

    /**
     * @return bool true when the maxLength facet is absent or well-formed; false when it raised a violation
     */
    private function validateMaxLength(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): bool
    {
        if ($value->maxLength === null) {
            return true;
        }

        if (!\is_int($value->maxLength) || $value->maxLength <= 0) {
            $this->context->buildViolation($constraint->maxLengthValueMessage)
                ->atPath('maxLength')
                ->addViolation();

            return false;
        }

        if ($value->type !== StyleOptionValueType::TYPE_STRING) {
            $this->context->buildViolation($constraint->maxLengthTypeMessage)
                ->atPath('maxLength')
                ->addViolation();

            return false;
        }

        return true;
    }

    private function validateAdminUI(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->adminUI === null || \is_array($value->adminUI)) {
            return;
        }

        $this->context->buildViolation($constraint->adminUiArrayMessage)
            ->atPath('adminUI')
            ->addViolation();
    }

    private function validateBreakpointAware(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        if ($value->breakpointAware === null || \is_bool($value->breakpointAware)) {
            return;
        }

        $this->context->buildViolation($constraint->breakpointAwareTypeMessage)
            ->atPath('breakpointAware')
            ->addViolation();
    }

    private function validateDefault(
        StyleOptionSpecificationDto $value,
        TypedStyleOption $constraint,
        bool $enumValid,
        bool $rangeValid,
        bool $maxLengthValid,
    ): void {
        if ($value->default === null) {
            return;
        }

        if (!$this->matchesType($value->default, $value->type)) {
            $this->context->buildViolation($constraint->defaultTypeMessage)
                ->setParameter('{{ type }}', $value->type)
                ->atPath('default')
                ->addViolation();

            return;
        }

        // The default is a scalar of the declared type; reject it when it falls outside any *valid* facet.
        // A malformed facet already raised its own violation, so skip it here to avoid a pile-on at default.
        if ($enumValid) {
            $this->validateDefaultAgainstEnum($value, $constraint);
        }

        if ($rangeValid) {
            $this->validateDefaultAgainstRange($value, $constraint);
        }

        if ($maxLengthValid) {
            $this->validateDefaultAgainstMaxLength($value, $constraint);
        }
    }

    private function validateDefaultAgainstEnum(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        // A valid enum is a non-empty list; the is_array guard also narrows it for in_array.
        if (!\is_array($value->enum) || \in_array($value->default, $value->enum, true)) {
            return;
        }

        $this->context->buildViolation($constraint->defaultEnumMessage)
            ->atPath('default')
            ->addViolation();
    }

    private function validateDefaultAgainstRange(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        // A valid range on a numeric type means the default is numeric too; the guards narrow for PHPStan.
        if (!\is_array($value->range) || (!\is_int($value->default) && !\is_float($value->default))) {
            return;
        }

        $min = $value->range['min'] ?? null;
        $max = $value->range['max'] ?? null;

        $belowMin = (\is_int($min) || \is_float($min)) && $value->default < $min;
        $aboveMax = (\is_int($max) || \is_float($max)) && $value->default > $max;

        if ($belowMin || $aboveMax) {
            $this->context->buildViolation($constraint->defaultRangeMessage)
                ->atPath('default')
                ->addViolation();
        }
    }

    private function validateDefaultAgainstMaxLength(StyleOptionSpecificationDto $value, TypedStyleOption $constraint): void
    {
        // The advisory default is bounded by the option's *effective* length cap: the declared maxLength, or
        // DEFAULT_STRING_MAX_LENGTH for an unbounded string (mirrors StyleOptionValueType::maxLength()), so a
        // default longer than the cap a client may store is rejected even when no maxLength is declared.
        if ($value->type !== StyleOptionValueType::TYPE_STRING || !\is_string($value->default)) {
            return;
        }

        $effectiveMax = \is_int($value->maxLength) && $value->maxLength > 0
            ? $value->maxLength
            : StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH;

        if (mb_strlen($value->default) > $effectiveMax) {
            $this->context->buildViolation($constraint->defaultMaxLengthMessage)
                ->atPath('default')
                ->addViolation();
        }
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
