<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidJsonFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ListFieldSerializer implements FieldSerializerInterface
{
    use FieldValidatorTrait;

    /**
     * @var ConstraintBuilder
     */
    protected $constraintBuilder;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var FieldSerializerRegistry
     */
    protected $compositeHandler;

    public function __construct(
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator,
        FieldSerializerRegistry $compositeHandler
    ) {
        $this->constraintBuilder = $constraintBuilder;
        $this->validator = $validator;
        $this->compositeHandler = $compositeHandler;
    }

    public function getFieldClass(): string
    {
        return ListField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ListField) {
            throw new InvalidSerializerFieldException(ListField::class, $field);
        }
        /** @var ListField $field */
        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $constraints = $this->constraintBuilder
                ->isArray()
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        $value = $data->getValue();

        if ($value !== null) {
            $value = array_values($value);

            if ($field->getFieldType()) {
                $this->validateTypes($field, $value, $parameters);
            }

            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    protected function validateTypes(ListField $field, array $values, WriteParameterBag $parameters): void
    {
        $fieldType = $field->getFieldType();
        $exceptions = [];
        $existence = new EntityExistence('', [], false, false, false, []);

        /** @var Field $listField */
        $listField = new $fieldType('key', 'key');

        $nestedParameters = $parameters->cloneForSubresource(
            $parameters->getDefinition(),
            $parameters->getPath() . '/' . $field->getPropertyName()
        );

        foreach ($values as $i => $value) {
            try {
                $kvPair = new KeyValuePair((string) $i, $value, true);

                $x = $this->compositeHandler->encode($listField, $existence, $kvPair, $nestedParameters);
                iterator_to_array($x);
            } catch (InvalidFieldException $exception) {
                $exceptions[] = $exception;
            } catch (InvalidJsonFieldException $exception) {
                $exceptions = array_merge($exceptions, $exception->getExceptions());
            }
        }

        if (\count($exceptions)) {
            throw new InvalidJsonFieldException($parameters->getPath() . '/' . $field->getPropertyName(), $exceptions);
        }
    }
}
