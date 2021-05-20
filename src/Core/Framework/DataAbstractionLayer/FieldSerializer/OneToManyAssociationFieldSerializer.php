<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class OneToManyAssociationFieldSerializer implements FieldSerializerInterface
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
        if (!$field instanceof OneToManyAssociationField) {
            throw new InvalidSerializerFieldException(OneToManyAssociationField::class, $field);
        }

        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $data;
        }

        $id = $parameters->getContext()->get($parameters->getDefinition()->getClass(), $field->getLocalField());
        $reference = $field->getReferenceDefinition();

        $fkField = $reference->getFields()->getByStorageName($field->getReferenceField());

        // allows to reset the association for a none cascade delete
        $fk = $fkField->getPropertyName();

        foreach ($value as $keyValue => $subresources) {
            $currentId = $id;
            if (!\is_array($subresources)) {
                throw new ExpectedArrayException($parameters->getPath() . '/' . $key);
            }

            if (\array_key_exists($fk, $subresources) && $subresources[$fk] === null) {
                $currentId = null;
            }

            $subresources[$fk] = $currentId;

            $clonedParams = $parameters->cloneForSubresource(
                $reference,
                $parameters->getPath() . '/' . $key
            );

            $fkVersionField = $reference->getField($parameters->getDefinition()->getEntityName() . 'VersionId');
            if ($fkVersionField !== null) {
                $subresources = $fkVersionField->getSerializer()->normalize($fkVersionField, $subresources, $clonedParams);
            }
            $subresources = $this->writeExtractor->normalizeSingle($reference, $subresources, $clonedParams);

            $value[$keyValue] = $subresources;
        }

        $data[$key] = $value;

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof OneToManyAssociationField) {
            throw new InvalidSerializerFieldException(OneToManyAssociationField::class, $field);
        }
        $value = $data->getValue();

        if ($value === null) {
            yield from [];

            return;
        }

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath() . '/' . $data->getKey());
        }

        $this->map($field, $parameters, $data);

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

    private function map(OneToManyAssociationField $field, WriteParameterBag $parameters, KeyValuePair $data): void
    {
        $reference = $field->getReferenceDefinition();

        foreach ($data->getValue() as $keyValue => $subresources) {
            $this->writeExtractor->extract(
                $subresources,
                $parameters->cloneForSubresource(
                    $reference,
                    $parameters->getPath() . '/' . $data->getKey() . '/' . $keyValue
                )
            );
        }
    }
}
