<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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

    /**
     * @var DefinitionInstanceRegistry
     */
    protected $definitionRegistry;

    public function __construct(ValidatorInterface $validator, DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->validator = $validator;
        $this->definitionRegistry = $definitionRegistry;
    }

    protected function validate(
        array $constraints,
        KeyValuePair $data,
        string $path
    ): void {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator->validate($data->getValue(), $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $fieldName = $data->getKey();

                // correct pointer for json fields with pre-defined structure
                if ($violation->getPropertyPath()) {
                    $property = \str_replace('][', '/', $violation->getPropertyPath());
                    $property = \trim($property, '][');
                    $fieldName .= '/' . $property;
                }

                $fieldName = '/' . $fieldName;

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
            throw new WriteConstraintViolationException($violationList, $path);
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

        if ($existence->hasEntityName()
            && $this->definitionRegistry->getByEntityName($existence->getEntityName()) instanceof EntityTranslationDefinition
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

    protected function validateIfNeeded(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): void
    {
        if (!$this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            return;
        }

        $constraints = $this->getConstraints($field);

        $this->validate($constraints, $data, $parameters->getPath());
    }

    /**
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        return [];
    }
}
