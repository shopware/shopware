<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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

        $keyField = $parameters->getDefinition()->getFields()->getByStorageName($field->getStorageName());
        $reference = $field->getReferenceDefinition();

        //owning side?
        if ($keyField instanceof FkField) {
            $id = $this->mapOwningSide($reference, $field->getReferenceField(), $data, $parameters);

            yield $keyField->getPropertyName() => $id;

            return;
        }

        /* @var OneToOneAssociationField $field */
        $id = $parameters->getContext()->get($parameters->getDefinition()->getClass(), $field->getStorageName());

        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath() . '/' . $data->getKey());
        }

        $keyField = $reference->getFields()->getByStorageName(
            $field->getReferenceField()
        );

        $value[$keyField->getPropertyName()] = $id;

        $this->writeExtractor->extract(
            $value,
            $parameters->cloneForSubresource(
                $reference,
                $parameters->getPath() . '/' . $data->getKey()
            )
        );
    }

    public function decode(Field $field, $value): void
    {
        throw new DecodeByHydratorException($field);
    }

    private function mapOwningSide(
        EntityDefinition $reference,
        string $referenceField,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ) {
        $value = $data->getValue();

        $pkField = $reference->getFields()->getByStorageName($referenceField);

        //id provided? otherwise set new one to return it and yield the id into the FkField
        if (isset($value[$pkField->getPropertyName()])) {
            $id = $value[$pkField->getPropertyName()];
        } else {
            $id = Uuid::randomHex();
            $value[$pkField->getPropertyName()] = $id;
        }

        $this->writeExtractor->extract(
            $value,
            $parameters->cloneForSubresource(
                $reference,
                $parameters->getPath() . '/' . $data->getKey()
            )
        );

        return $id;
    }
}
