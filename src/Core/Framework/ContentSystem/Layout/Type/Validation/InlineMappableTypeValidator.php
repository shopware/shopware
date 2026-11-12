<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Enforces the two declaration rules for `inlineMappable`.
 *
 * The type rule mirrors {@see TranslatableTypeValidator}: token interpolation writes a string back into a string,
 * so the property must be declared as the lone type `string`; a union, `[string]` included, is rejected.
 *
 * Reject declarations carrying both `mappable` and `inlineMappable`, because they would offer two mapping
 * mechanisms for one value.
 *
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
final class InlineMappableTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof InlineMappableType) {
            throw new UnexpectedTypeException($constraint, InlineMappableType::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value->inlineMappable) {
            return;
        }

        if ($value->type !== 'string') {
            $this->context->buildViolation($constraint->message)
                ->atPath('inlineMappable')
                ->addViolation();
        }

        if ($value->mappable) {
            $this->context->buildViolation($constraint->exclusiveMessage)
                ->atPath('inlineMappable')
                ->addViolation();
        }
    }
}
