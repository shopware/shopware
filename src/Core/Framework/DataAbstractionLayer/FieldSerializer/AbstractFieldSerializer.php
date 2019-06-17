<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    protected function validate(
        array $constraints,
        KeyValuePair $data,
        string $path
    ): void {
        $violationList = new ConstraintViolationList();
        $fieldName = $data->getKey();

        foreach ($constraints as $constraint) {
            $violations = $this->validator->validate($data->getValue(), $constraint);

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
            throw new WriteConstraintViolationException($violationList, $path . '/' . $fieldName);
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

        if ($existence->isChild() && $this->isInherited($field, $parameters)) {
            return false;
        }

        if ($existence->hasDefinition() && $existence->getDefinition() instanceof EntityTranslationDefinition
            && $parameters->getCurrentWriteLanguageId() !== Defaults::LANGUAGE_SYSTEM
        ) {
            return false;
        }

        return $field->is(Required::class);
    }

    protected function isInherited(Field $field, WriteParameterBag $parameters): bool
    {
        if ($parameters->getDefinition()->isInheritanceAware()) {
            return $field->is(Inherited::class);
        }

        if (!$parameters->getDefinition() instanceof EntityTranslationDefinition) {
            return false;
        }

        $parent = $parameters->getDefinition()->getParentDefinition();

        $field = $parent->getFields()->get($field->getPropertyName());

        return $field->is(Inherited::class);
    }

    /**
     * @param Constraint[] $constraints
     */
    protected function validateIfNeeded(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters, ?array $constraints = null): void
    {
        if (!$this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            return;
        }

        if ($constraints === null) {
            $constraints = $this->getConstraints();
        }

        $this->validate($constraints, $data, $parameters->getPath());
    }

    protected function getConstraints(): array
    {
        return [];
    }
}
