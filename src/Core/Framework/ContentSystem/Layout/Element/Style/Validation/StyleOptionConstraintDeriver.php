<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Turns a declarative StyleOptionValueType into the Symfony constraints a single per-breakpoint
 * value must satisfy, via the fluent ConstraintBuilder. NotBlank applies to every type except
 * boolean, where false is a legitimate value.
 *
 * @internal
 */
#[Package('framework')]
final class StyleOptionConstraintDeriver
{
    /**
     * @return list<Constraint>
     */
    public function derive(StyleOptionValueType $valueType): array
    {
        $builder = new ConstraintBuilder();

        $this->applyTypeConstraint($builder, $valueType->type());

        if ($valueType->type() !== StyleOptionValueType::TYPE_BOOLEAN) {
            $builder->isNotBlank();
        }

        $range = $valueType->range();
        if ($range !== null) {
            $builder->addConstraint(new Range(min: $range['min'] ?? null, max: $range['max'] ?? null));
        }

        $maxLength = $valueType->maxLength();
        if ($maxLength === null && $valueType->type() === StyleOptionValueType::TYPE_NUMBER) {
            // A number arrives as is_numeric() input, so a numeric *string* of unbounded length would pass
            // Type('numeric'); cap its serialized length the same way strings are capped, so a client cannot
            // store a megabyte value in the layout JSON column. A real int/float stays well within the cap.
            $maxLength = StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH;
        }
        if ($maxLength !== null) {
            $builder->isLengthLessThanOrEqual($maxLength);
        }

        $enum = $valueType->enum();
        if ($enum !== null) {
            $builder->addConstraint(new Choice(choices: $enum, strict: true));
        }

        return array_values($builder->getConstraints());
    }

    private function applyTypeConstraint(ConstraintBuilder $builder, string $type): void
    {
        match ($type) {
            StyleOptionValueType::TYPE_BOOLEAN => $builder->isBool(),
            StyleOptionValueType::TYPE_INTEGER => $builder->isInt(),
            StyleOptionValueType::TYPE_NUMBER => $builder->isNumeric(),
            StyleOptionValueType::TYPE_STRING => $builder->isString(),
            default => throw ContentSystemException::unsupportedStyleValueType($type),
        };
    }
}
