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
final class StructuredPropertyTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StructuredPropertyType) {
            throw new UnexpectedTypeException($constraint, StructuredPropertyType::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (\is_array($value->type)) {
            if (!array_is_list($value->type) || $value->type === []) {
                $this->context->buildViolation($constraint->typeListMessage)
                    ->atPath('type')
                    ->addViolation();

                return;
            }
        }

        $types = $this->normalizeTypes($value->type);

        foreach ($types as $type) {
            if ($type === '') {
                $this->context->buildViolation($constraint->typeEntryMessage)
                    ->atPath('type')
                    ->addViolation();

                return;
            }
        }

        if (\count(array_unique($types)) !== \count($types)) {
            $this->context->buildViolation($constraint->duplicateTypeMessage)
                ->atPath('type')
                ->addViolation();

            return;
        }

        $hasObject = \in_array('object', $types, true);

        if ($hasObject && ($value->properties === null || $value->properties === [])) {
            $this->context->buildViolation($constraint->objectRequiresPropertiesMessage)
                ->atPath('properties')
                ->addViolation();

            return;
        }

        if (!$hasObject && $value->properties !== null) {
            $this->context->buildViolation($constraint->propertiesRequireObjectTypeMessage)
                ->atPath('properties')
                ->addViolation();
        }
    }

    /**
     * @param string|array<mixed> $type
     *
     * @return list<string>
     */
    private function normalizeTypes(string|array $type): array
    {
        if (\is_string($type)) {
            return [$type];
        }

        return array_values(array_map(static function (mixed $entry): string {
            if (!\is_string($entry)) {
                return '';
            }

            return $entry;
        }, $type));
    }
}
