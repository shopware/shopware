<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CatalogFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function getFieldClass(): string
    {
        return CatalogField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof CatalogField) {
            throw new InvalidSerializerFieldException(CatalogField::class, $field);
        }
        if ($parameters->getContext()->has($parameters->getDefinition(), 'catalogId')) {
            $value = $parameters->getContext()->get($parameters->getDefinition(), 'catalogId');
        } elseif (!empty($data->getValue())) {
            $value = $data->getValue();
        } else {
            $value = Defaults::CATALOG;
        }

        $restriction = $parameters->getContext()->getContext()->getCatalogIds();

        //user has restricted catalog access
        if (\is_array($restriction)) {
            $this->validateCatalog($restriction, $value, $parameters->getPath());
        }

        //write catalog id of current object to write context
        $parameters->getContext()->set($parameters->getDefinition(), 'catalogId', $value);
        if ($parameters->getDefinition()::getTranslationDefinitionClass()) {
            $parameters->getContext()->set($parameters->getDefinition()::getTranslationDefinitionClass(), 'catalogId', $value);
        }

        /* @var CatalogField $field */
        yield $field->getStorageName() => Uuid::fromStringToBytes($value);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }

    protected function validateCatalog(array $restrictedCatalogs, $catalogId, string $path): void
    {
        $violationList = new ConstraintViolationList();
        $violations = $this->validator->validate($catalogId, [new Choice(['choices' => $restrictedCatalogs])]);

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $violationList->add(
                new ConstraintViolation(
                    sprintf('No access to catalog id: %s', $catalogId),
                    'No access to catalog id: {{ value }}',
                    $violation->getParameters(),
                    $violation->getRoot(),
                    'catalogId',
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation->getConstraint(),
                    $violation->getCause()
                )
            );
        }

        if (\count($violationList)) {
            throw new InvalidFieldException($violationList, $path . '/catalogId');
        }
    }
}
