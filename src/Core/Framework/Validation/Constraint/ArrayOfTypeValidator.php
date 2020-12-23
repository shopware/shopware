<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ArrayOfTypeValidator extends ConstraintValidator
{
    public function validate($values, Constraint $constraint): void
    {
        if (!$constraint instanceof ArrayOfType) {
            throw new UnexpectedTypeException($constraint, Uuid::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($values === null) {
            return;
        }

        if (!\is_array($values)) {
            $this->context->buildViolation($constraint::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        foreach ($values as $value) {
            $type = mb_strtolower($constraint->type);
            $type = $type === 'boolean' ? 'bool' : $constraint->type;
            $isFunction = 'is_' . $type;
            $ctypeFunction = 'ctype_' . $type;

            if (\function_exists($isFunction) && $isFunction($value)) {
                continue;
            }

            if (\function_exists($ctypeFunction) && $ctypeFunction($value)) {
                continue;
            }

            if ($value instanceof $constraint->type) {
                continue;
            }

            if (\is_array($value)) {
                $value = print_r($value, true);
            }

            $this->context->buildViolation($constraint::INVALID_MESSAGE)
                ->setCode(Type::INVALID_TYPE_ERROR)
                ->setParameter('{{ type }}', $constraint->type)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
