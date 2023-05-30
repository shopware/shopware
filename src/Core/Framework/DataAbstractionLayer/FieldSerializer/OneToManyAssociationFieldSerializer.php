<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class OneToManyAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WriteCommandExtractor $writeExtractor
    ) {
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof OneToManyAssociationField) {
            throw DataAbstractionLayerException::invalidSerializerField(OneToManyAssociationField::class, $field);
        }

        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $data;
        }

        $id = $parameters->getContext()->get($parameters->getDefinition()->getEntityName(), $field->getLocalField());
        $reference = $field->getReferenceDefinition();

        $fkField = $reference->getFields()->getByStorageName($field->getReferenceField());

        if (!$fkField) {
            throw new \RuntimeException(sprintf('Can not find fk field for accessor %s.%s', $reference->getEntityName(), $field->getReferenceField()));
        }

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
            throw DataAbstractionLayerException::invalidSerializerField(OneToManyAssociationField::class, $field);
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

    public function decode(Field $field, mixed $value): never
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
