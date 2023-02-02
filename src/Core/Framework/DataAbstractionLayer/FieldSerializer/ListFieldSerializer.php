<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class ListFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * @throws InvalidSerializerFieldException
     */
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ListField) {
            throw new InvalidSerializerFieldException(ListField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value !== null) {
            $value = array_values($value);

            $this->validateTypes($field, $value, $parameters);

            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array|null
     *
     * @deprecated tag:v6.5.0 - reason:return-type-change - The return type will be native typed
     */
    public function decode(Field $field, $value)/* ?array*/
    {
        if ($value === null) {
            return null;
        }

        /** @deprecated tag:v6.5.0 - remove whole is block as the `strict` behaviour will always be used */
        if (!Feature::isActive('v6.5.0.0')) {
            if ($field instanceof ListField && $field->isStrict()) {
                return array_values(json_decode($value, true));
            }

            return json_decode($value, true);
        }

        return array_values(json_decode($value, true));
    }

    protected function getConstraints(Field $field): array
    {
        return [new Type('array')];
    }

    protected function validateTypes(ListField $field, array $values, WriteParameterBag $parameters): void
    {
        $fieldType = $field->getFieldType();
        if ($fieldType === null) {
            return;
        }

        $existence = new EntityExistence(null, [], false, false, false, []);

        /** @var Field $listField */
        $listField = new $fieldType('key', 'key');
        $listField->compile($this->definitionRegistry);

        $nestedParameters = $parameters->cloneForSubresource(
            $parameters->getDefinition(),
            $parameters->getPath() . '/' . $field->getPropertyName()
        );

        foreach ($values as $i => $value) {
            try {
                $kvPair = new KeyValuePair((string) $i, $value, true);

                $x = $listField->getSerializer()->encode($listField, $existence, $kvPair, $nestedParameters);
                $_x = iterator_to_array($x);
            } catch (WriteFieldException $exception) {
                $parameters->getContext()->getExceptions()->add($exception);
            }
        }
    }
}
