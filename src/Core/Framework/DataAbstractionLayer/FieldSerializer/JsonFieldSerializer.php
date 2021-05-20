<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JsonFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * mariadbs `JSON_VALID` function does not allow escaped unicode.
     */
    public static function encodeJson($value, int $options = \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION): string
    {
        return json_encode($value, $options);
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

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue() ?? $field->getDefault();

        if ($value !== null && !empty($field->getPropertyMapping())) {
            $value = $this->validateMapping($field, $value, $parameters);
        }

        if ($value !== null) {
            $value = self::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array|null
     *
     * @deprecated tag:v6.5.0 The parameter $value and return type will be native typed
     */
    public function decode(Field $field, /*?string */$value)/*: ?array*/
    {
        if (!$field instanceof JsonField) {
            throw new InvalidSerializerFieldException(JsonField::class, $field);
        }

        if ($value === null) {
            return $field->getDefault();
        }

        $raw = json_decode($value, true);
        $decoded = $raw;
        if (empty($field->getPropertyMapping())) {
            return $raw;
        }

        foreach ($field->getPropertyMapping() as $embedded) {
            $key = $embedded->getPropertyName();
            if (!isset($raw[$key])) {
                continue;
            }
            $value = $embedded instanceof JsonField
                ? self::encodeJson($raw[$key])
                : $raw[$key];

            $embedded->compile($this->definitionRegistry);
            $decodedValue = $embedded->getSerializer()->decode($embedded, $value);
            if ($decodedValue instanceof \DateTimeInterface) {
                $format = $embedded instanceof DateField ? Defaults::STORAGE_DATE_FORMAT : \DATE_ATOM;
                $decodedValue = $decodedValue->format($format);
            }

            $decoded[$key] = $decodedValue;
        }

        return $decoded;
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type('array'),
            new NotNull(),
        ];
    }

    protected function validateMapping(
        JsonField $field,
        array $data,
        WriteParameterBag $parameters
    ): array {
        if (\array_key_exists('_class', $data)) {
            unset($data['_class']);
        }

        $stack = new DataStack($data);
        $existence = new EntityExistence(null, [], false, false, false, []);
        $fieldPath = $parameters->getPath() . '/' . $field->getPropertyName();

        $propertyKeys = array_map(function (Field $field) {
            return $field->getPropertyName();
        }, $field->getPropertyMapping());

        // If a mapping is defined, you should not send properties that are undefined.
        // Sending undefined fields will throw an UnexpectedFieldException
        $keyDiff = array_diff(array_keys($data), $propertyKeys);
        if (\count($keyDiff)) {
            foreach ($keyDiff as $fieldName) {
                $parameters->getContext()->getExceptions()->add(
                    new UnexpectedFieldException($fieldPath . '/' . $fieldName, (string) $fieldName)
                );
            }
        }

        foreach ($field->getPropertyMapping() as $nestedField) {
            $kvPair = $stack->pop($nestedField->getPropertyName());

            if ($kvPair === null) {
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
                $parameters->getCommandQueue()
            );

            /*
             * Dont call encode on nested JsonFields if they are not typed. This also allows directly storing
             * non-array values like strings.
             */
            if ($nestedField instanceof JsonField && empty($nestedField->getPropertyMapping())) {
                $stack->update($kvPair->getKey(), $kvPair->getValue());

                continue;
            }

            try {
                $nestedField->compile($this->definitionRegistry);
                $encoded = $nestedField->getSerializer()->encode($nestedField, $existence, $kvPair, $nestedParams);

                foreach ($encoded as $fieldKey => $fieldValue) {
                    if ($nestedField instanceof JsonField && $fieldValue !== null) {
                        $fieldValue = json_decode($fieldValue, true);
                    }

                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (WriteFieldException $exception) {
                $parameters->getContext()->getExceptions()->add($exception);
            }
        }

        return $stack->getResultAsArray();
    }
}
