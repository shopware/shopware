<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
final class TranslatableTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TranslatableType) {
            throw new UnexpectedTypeException($constraint, TranslatableType::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if ($value->translatable && $this->normalizeTypes($value->type) !== ['string']) {
            $this->context->buildViolation($constraint->message)
                ->atPath('translatable')
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
