<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal
 */
#[Package('framework')]
final class ValidPropertyConstraintsValidator extends ConstraintValidator
{
    /**
     * Subset of types that support `enum` and `translatable` constraints.
     */
    private const PRIMITIVE_TYPES = ['string', 'integer', 'boolean', 'number'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPropertyConstraints) {
            throw new UnexpectedTypeException($constraint, ValidPropertyConstraints::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof PropertySpecificationDto) {
            throw new UnexpectedTypeException($value, PropertySpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if ($value->translatable && $value->type !== 'string') {
            $this->context->buildViolation($constraint->translatableMessage)
                ->atPath('translatable')
                ->addViolation();
        }

        if ($value->enum !== null) {
            if (!\in_array($value->type, self::PRIMITIVE_TYPES, true)) {
                $this->context->buildViolation($constraint->enumTypeMessage)
                    ->atPath('enum')
                    ->addViolation();
            }

            if (!array_is_list($value->enum)) {
                $this->context->buildViolation($constraint->enumListMessage)
                    ->atPath('enum')
                    ->addViolation();
            }
        }
    }
}
