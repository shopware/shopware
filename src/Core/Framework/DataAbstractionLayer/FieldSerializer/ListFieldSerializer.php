<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('core')]
class ListFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @throws DataAbstractionLayerException
     */
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ListField) {
            throw DataAbstractionLayerException::invalidSerializerField(ListField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value !== null) {
            $value = array_values($value);

            $this->validateTypes($field, $value, $parameters);

            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return array_values(json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR));
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

        $existence = EntityExistence::createEmpty();

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
