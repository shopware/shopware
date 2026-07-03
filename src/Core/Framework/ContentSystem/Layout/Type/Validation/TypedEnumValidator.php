<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
final class TypedEnumValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TypedEnum) {
            throw new UnexpectedTypeException($constraint, TypedEnum::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if ($value->enum === null) {
            return;
        }

        $primitiveType = $this->getSinglePrimitiveType($value->type);

        if ($primitiveType === null) {
            $this->context->buildViolation($constraint->typeMessage)
                ->atPath('enum')
                ->addViolation();

            return;
        }

        if (!array_is_list($value->enum)) {
            $this->context->buildViolation($constraint->listMessage)
                ->atPath('enum')
                ->addViolation();
        }

        foreach ($value->enum as $enumValue) {
            if (!$this->matchesType($enumValue, $primitiveType)) {
                $this->context->buildViolation($constraint->valueTypeMessage)
                    ->setParameter('{{ type }}', $primitiveType)
                    ->atPath('enum')
                    ->addViolation();

                break;
            }
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

    /**
     * @param string|list<string> $type
     */
    private function getSinglePrimitiveType(string|array $type): ?string
    {
        $types = \is_string($type) ? [$type] : array_values($type);

        if (\count($types) !== 1) {
            return null;
        }

        $resolvedType = $types[0];

        if (!\in_array($resolvedType, PropertyType::PRIMITIVE_TYPES, true)) {
            return null;
        }

        return $resolvedType;
    }
}
