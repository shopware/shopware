<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class ManyToOneAssociationFieldSerializer implements FieldSerializerInterface
{
    protected WriteCommandExtractor $writeExtractor;

    public function __construct(WriteCommandExtractor $writeExtractor)
    {
        $this->writeExtractor = $writeExtractor;
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof ManyToOneAssociationField) {
            throw new InvalidSerializerFieldException(ManyToOneAssociationField::class, $field);
        }

        $referenceField = $field->getReferenceDefinition()->getFields()->getByStorageName($field->getReferenceField());
        if ($referenceField === null) {
            throw new \RuntimeException(
                sprintf(
                    'Could not find reference field "%s" from definition "%s"',
                    $field->getReferenceField(),
                    \get_class($field->getReferenceDefinition())
                )
            );
        }
        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $data;
        }

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath());
        }

        $fkField = $parameters->getDefinition()->getFields()->getByStorageName($field->getStorageName());
        if ($fkField === null) {
            throw new \RuntimeException(
                sprintf(
                    'Could not find FK field "%s" from field "%s"',
                    $field->getStorageName(),
                    \get_class($parameters->getDefinition())
                )
            );
        }

        $isPrimary = $fkField->is(PrimaryKey::class);

        if (isset($value[$referenceField->getPropertyName()])) {
            $id = $value[$referenceField->getPropertyName()];

        // plugins can add a ManyToOne where they define that the local/storage column is the primary and the reference is the foreign key
            // in this case we have a reversed many to one association configuration
        } elseif ($isPrimary) {
            $id = $parameters->getContext()->get($parameters->getDefinition()->getClass(), $fkField->getPropertyName());
        } else {
            $id = Uuid::randomHex();
            $value[$referenceField->getPropertyName()] = $id;
        }

        $clonedParams = $parameters->cloneForSubresource(
            $field->getReferenceDefinition(),
            $parameters->getPath() . '/' . $key
        );

        $value = $this->writeExtractor->normalizeSingle($field->getReferenceDefinition(), $value, $clonedParams);

        // in case of a reversed many to one configuration we have to return nothing, otherwise the primary key would be overwritten
        if (!$isPrimary) {
            $data[$fkField->getPropertyName()] = $id;
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
        if (!$field instanceof ManyToOneAssociationField) {
            throw new InvalidSerializerFieldException(ManyToOneAssociationField::class, $field);
        }

        if (!\is_array($data->getValue())) {
            throw new ExpectedArrayException($parameters->getPath());
        }

        $this->writeExtractor->extract(
            $data->getValue(),
            $parameters->cloneForSubresource(
                $field->getReferenceDefinition(),
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
