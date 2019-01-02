<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidJsonFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JsonFieldSerializer implements FieldSerializerInterface
{
    use FieldValidatorTrait;

    protected $fieldHandlerRegistry;

    /**
     * @var ConstraintBuilder
     */
    protected $constraintBuilder;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(
        FieldSerializerRegistry $compositeHandler,
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator
    ) {
        $this->fieldHandlerRegistry = $compositeHandler;
        $this->constraintBuilder = $constraintBuilder;
        $this->validator = $validator;
    }

    public function getFieldClass(): string
    {
        return JsonField::class;
    }

    /**
     * mariadbs `JSON_VALID` function does not allow escaped unicode.
     */
    public static function encodeJson($value, int $options = JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION): string
    {
        return \json_encode($value, $options);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof JsonField) {
            throw new InvalidSerializerFieldException(JsonField::class, $field);
        }
        $value = $data->getValue();

        /** @var JsonField $field */
        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $constraints = $this->getConstraints($parameters);

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        if (!empty($field->getPropertyMapping()) && $data->getValue() !== null) {
            $value = $this->validateMapping($field, $data->getValue(), $parameters);
        }

        if ($value !== null) {
            $value = self::encodeJson($value);
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

    protected function getConstraints(WriteParameterBag $parameters): array
    {
        return $this->constraintBuilder
            ->isArray()
            ->getConstraints();
    }

    protected function validateMapping(
        JsonField $field,
        array $data,
        WriteParameterBag $parameters
    ): array {
        if (array_key_exists('_class', $data)) {
            unset($data['_class']);
        }

        $exceptions = [];
        $stack = new DataStack($data);
        $existence = new EntityExistence('', [], false, false, false, []);
        $fieldPath = $parameters->getPath() . '/' . $field->getPropertyName();

        $propertyKeys = array_map(function (Field $field) {
            return $field->getPropertyName();
        }, $field->getPropertyMapping());

        // If a mapping is defined, you should not send properties that are undefined.
        // Sending undefined fields will throw an UnexpectedFieldException
        $keyDiff = array_diff(array_keys($data), $propertyKeys);
        if (\count($keyDiff)) {
            foreach ($keyDiff as $fieldName) {
                $exceptions[] = new UnexpectedFieldException($fieldPath . '/' . $fieldName, $fieldName);
            }
        }

        foreach ($field->getPropertyMapping() as $nestedField) {
            try {
                $kvPair = $stack->pop($nestedField->getPropertyName());
            } catch (ExceptionNoStackItemFound $e) {
                // The writer updates the whole field, so there is no possibility to update
                // "some" fields. To enable a merge, we have to respect the $existence state
                // for correct constraint validation. In addition the writer has to be rewritten
                // in order to handle merges.
                if (!$nestedField->is(Required::class)) {
                    continue;
                }

                $kvPair = new KeyValuePair($nestedField->getPropertyName(), null, true);
            }

            $nestedParams = new WriteParameterBag(
                $parameters->getDefinition(),
                $parameters->getContext(),
                $parameters->getPath() . '/' . $field->getPropertyName(),
                $parameters->getCommandQueue(),
                $parameters->getExceptionStack()
            );

            try {
                $encoded = $this->fieldHandlerRegistry->encode($nestedField, $existence, $kvPair, $nestedParams);

                foreach ($encoded as $fieldKey => $fieldValue) {
                    if ($nestedField instanceof JsonField) {
                        $fieldValue = json_decode($fieldValue, true);
                    }

                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (InvalidFieldException $exception) {
                $exceptions[] = $exception;
            } catch (InvalidJsonFieldException $exception) {
                $exceptions = array_merge($exceptions, $exception->getExceptions());
            }
        }

        if (\count($exceptions)) {
            throw new InvalidJsonFieldException($parameters->getPath() . '/' . $field->getPropertyName(), $exceptions);
        }

        return $stack->getResultAsArray();
    }
}
