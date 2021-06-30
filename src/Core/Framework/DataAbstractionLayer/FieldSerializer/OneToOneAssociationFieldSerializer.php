<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class OneToOneAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtractor;

    public function __construct(
        WriteCommandExtractor $writeExtractor
    ) {
        $this->writeExtractor = $writeExtractor;
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof OneToOneAssociationField) {
            throw new InvalidSerializerFieldException(OneToOneAssociationField::class, $field);
        }

        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $data;
        }

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath());
        }

        $keyField = $parameters->getDefinition()->getFields()->getByStorageName($field->getStorageName());
        $reference = $field->getReferenceDefinition();

        if ($keyField instanceof FkField) {
            $referenceField = $field->getReferenceField();
            $pkField = $reference->getFields()->getByStorageName($referenceField);

            //id provided? otherwise set new one to return it and yield the id into the FkField
            if (isset($value[$pkField->getPropertyName()])) {
                $id = $value[$pkField->getPropertyName()];
            } else {
                $id = Uuid::randomHex();
                $value[$pkField->getPropertyName()] = $id;
            }

            $data[$keyField->getPropertyName()] = $id;
        } else {
            $id = $parameters->getContext()->get($parameters->getDefinition()->getClass(), $field->getStorageName());
            $keyField = $reference->getFields()->getByStorageName($field->getReferenceField());

            $value[$keyField->getPropertyName()] = $id;
        }

        $clonedParams = $parameters->cloneForSubresource(
            $field->getReferenceDefinition(),
            $parameters->getPath() . '/' . $key
        );

        $value = $this->writeExtractor->normalizeSingle($field->getReferenceDefinition(), $value, $clonedParams);

        $data[$key] = $value;

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof OneToOneAssociationField) {
            throw new InvalidSerializerFieldException(OneToOneAssociationField::class, $field);
        }

        if (!\is_array($data->getValue())) {
            throw new ExpectedArrayException($parameters->getPath());
        }

        $reference = $field->getReferenceDefinition();
        $value = $data->getValue();

        $this->writeExtractor->extract(
            $value,
            $parameters->cloneForSubresource(
                $reference,
                $parameters->getPath() . '/' . $data->getKey()
            )
        );

        yield from [];
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     * @never
     */
    public function decode(Field $field, /*?string */$value): void
    {
        throw new DecodeByHydratorException($field);
    }
}
