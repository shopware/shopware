<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Enforces the three declaration rules for `inlineMappable`.
 *
 * The type rule mirrors {@see TranslatableTypeValidator}: token interpolation writes a string back into a string,
 * so the property must be declared as the lone type `string`; a union, `[string]` included, is rejected.
 *
 * Reject declarations carrying both `mappable` and `inlineMappable`, because they would offer two mapping
 * mechanisms for one value.
 *
 * Reject the flag on a member nested inside an object property: only top-level properties are inspected and expanded.
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

        // The nested members are validated through the cascade on `properties`, so this check sees each member
        // exactly once, from its direct parent. Inline mapping inspects and expands top-level properties only, so a
        // nested declaration would promise a behaviour that never runs.
        foreach ($value->properties ?? [] as $key => $member) {
            if ($member->inlineMappable) {
                $this->context->buildViolation($constraint->nestedMessage)
                    ->atPath(\sprintf('properties[%s].inlineMappable', $key))
                    ->addViolation();
            }
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
