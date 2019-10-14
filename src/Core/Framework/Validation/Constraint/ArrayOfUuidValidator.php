<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid as ArrayOfUuidConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ArrayOfUuidValidator extends ConstraintValidator
{
    public function validate($values, Constraint $constraint): void
    {
        if (!$constraint instanceof ArrayOfUuidConstraint) {
            throw new UnexpectedTypeException($constraint, Uuid::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($values === null || empty($values)) {
            return;
        }

        if (!is_array($values)) {
            $this->context->buildViolation($constraint::INVALID_TYPE_MESSAGE)
                ->setCode(Type::INVALID_TYPE_ERROR)
                ->addViolation();

            return;
        }

        foreach ($values as $value) {
            if (!is_string($value) || !Uuid::isValid($value)) {
                $this->context->buildViolation($constraint::INVALID_MESSAGE)
                    ->setCode(ArrayOfUuid::INVALID_TYPE_CODE)
                    ->setParameter('{{ string }}', $value)
                    ->addViolation();
            }
        }
    }
}
