<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ArrayOfTypeValidator extends ConstraintValidator
{
    public function validate($values, Constraint $constraint)
    {
        if (!$constraint instanceof ArrayOfType) {
            throw new UnexpectedTypeException($constraint, Uuid::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($values === null) {
            return;
        }

        if (!is_array($values)) {
            $this->context->buildViolation($constraint::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        foreach ($values as $value) {
            $type = strtolower($constraint->type);
            $type = $type == 'boolean' ? 'bool' : $constraint->type;
            $isFunction = 'is_' . $type;
            $ctypeFunction = 'ctype_' . $type;

            if (\function_exists($isFunction) && $isFunction($value)) {
                continue;
            } elseif (\function_exists($ctypeFunction) && $ctypeFunction($value)) {
                continue;
            } elseif ($value instanceof $constraint->type) {
                continue;
            }

            if (is_array($value)) {
                $value = print_r($value, true);
            }

            $this->context->buildViolation($constraint::INVALID_MESSAGE)
                ->setParameter('{{ type }}', $constraint->type)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
