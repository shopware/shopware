<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[Package('core')]
class ArrayOfTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ArrayOfType) {
            throw new UnexpectedTypeException($constraint, Uuid::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($value === null) {
            return;
        }

        if (!\is_array($value)) {
            $this->context->buildViolation(ArrayOfType::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        foreach ($value as $item) {
            $type = mb_strtolower($constraint->type);
            $type = $type === 'boolean' ? 'bool' : $constraint->type;
            $isFunction = 'is_' . $type;
            $ctypeFunction = 'ctype_' . $type;

            if (\function_exists($isFunction) && $isFunction($item)) { /* @phpstan-ignore-line */
                continue;
            }

            if (\function_exists($ctypeFunction) && $ctypeFunction($item)) { /* @phpstan-ignore-line */
                continue;
            }

            if ($item instanceof $constraint->type) {
                continue;
            }

            if (\is_array($item)) {
                $item = print_r($item, true);
            }

            $this->context->buildViolation(ArrayOfType::INVALID_MESSAGE)
                ->setCode(Type::INVALID_TYPE_ERROR)
                ->setParameter('{{ type }}', $constraint->type)
                ->setParameter('{{ value }}', (string) $item)
                ->addViolation();
        }
    }
}
