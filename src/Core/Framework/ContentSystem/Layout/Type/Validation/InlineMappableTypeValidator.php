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
 * so the property must be declared exactly `string`.
 *
 * The exclusivity rule is the more load-bearing of the two. A property declaring both `mappable` and
 * `inlineMappable` would offer two mapping mechanisms for one value — a whole-field mapping shadowing the authored
 * text, and tokens inside that same text — leaving every reader (settings panel, write gate, render path) to invent
 * its own tie-break. Rejecting the declaration makes the conflict unrepresentable instead.
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

        if ($this->normalizeTypes($value->type) !== ['string']) {
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

    /**
     * @param string|list<string> $type
     *
     * @return list<string>
     */
    private function normalizeTypes(string|array $type): array
    {
        if (\is_string($type)) {
            return [$type];
        }

        return array_values($type);
    }
}
