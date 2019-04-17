<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait FieldValidatorTrait
{
    protected function validate(
        ValidatorInterface $validator,
        array $constraints,
        string $fieldName,
        $value,
        string $path
    ): void {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $validator->validate($value, $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                // correct pointer for json fields with pre-defined structure
                if ($violation->getPropertyPath()) {
                    $property = str_replace('][', '/', $violation->getPropertyPath());
                    $property = trim($property, '][');
                    $fieldName .= '/' . $property;
                }

                $violationList->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $fieldName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause()
                    )
                );
            }
        }

        if (\count($violationList)) {
            throw new InvalidFieldException($violationList, $path . '/' . $fieldName);
        }
    }

    protected function requiresValidation(
        Field $field,
        EntityExistence $existence,
        $value,
        WriteParameterBag $parameters
    ): bool {
        if ($value !== null) {
            return true;
        }

        if ($field->is(Inherited::class) && $existence->isChild()) {
            return false;
        }

        if (\is_subclass_of($existence->getDefinition(), EntityTranslationDefinition::class)
            && $parameters->getCurrentWriteLanguageId() !== Defaults::LANGUAGE_SYSTEM
        ) {
            return false;
        }

        return $field->is(Required::class);
    }
}
