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
final class TypedDefaultValidator extends ConstraintValidator
{
    private const PRIMITIVE_TYPES = ['string', 'integer', 'boolean', 'number'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TypedDefault) {
            throw new UnexpectedTypeException($constraint, TypedDefault::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if ($value->default === null) {
            return;
        }

        if (!\in_array($value->type, self::PRIMITIVE_TYPES, true)) {
            $this->context->buildViolation($constraint->nonPrimitiveMessage)
                ->atPath('default')
                ->addViolation();

            return;
        }

        if (!$this->matchesType($value->default, $value->type)) {
            $this->context->buildViolation($constraint->typeMessage)
                ->setParameter('{{ type }}', $value->type)
                ->atPath('default')
                ->addViolation();
        }
    }

    private function matchesType(string|int|float|bool $value, string $type): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'boolean' => \is_bool($value),
            'number' => \is_int($value) || \is_float($value),
            default => false,
        };
    }
}
