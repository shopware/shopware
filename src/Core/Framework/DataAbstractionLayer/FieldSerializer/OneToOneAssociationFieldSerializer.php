<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;

class OneToOneAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtractor;

    public function __construct(WriteCommandExtractor $writeExtractor)
    {
        $this->writeExtractor = $writeExtractor;
    }

    public function getFieldClass(): string
    {
        return OneToOneAssociationField::class;
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
            throw new MalformatDataException($parameters->getPath(), 'Expected array');
        }

        $keyField = $parameters->getDefinition()::getFields()->getByStorageName($field->getStorageName());

        //owning side?
        if ($keyField instanceof FkField) {
            $id = $this->mapOwningSide($keyField, $field->getReferenceClass(), $data, $parameters);

            yield $field->getStorageName() => $id;

            return;
        }

        /* @var OneToOneAssociationField $field */
        $id = $parameters->getContext()->get($parameters->getDefinition(), $field->getStorageName());

        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $data->getKey(), 'Value must be an array.');
        }

        $keyField = $field->getReferenceClass()::getFields()->getByStorageName(
            $field->getReferenceField()
        );

        $value[$keyField->getPropertyName()] = $id;

        $this->writeExtractor->extract(
            $value,
            $parameters->cloneForSubresource(
                $field->getReferenceClass(),
                $parameters->getPath() . '/' . $data->getKey()
            )
        );
    }

    public function decode(Field $field, $value): void
    {
        throw new DecodeByHydratorException($field);
    }

    private function mapOwningSide(
        FkField $foreignKey,
        string $referenceClass,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ) {
        $value = $data->getValue();
        //id provided? otherwise set new one to return it and yield the id into the FkField
        if (isset($value[$foreignKey->getPropertyName()])) {
            $id = $value[$foreignKey->getPropertyName()];
        } else {
            $id = Uuid::uuid4()->getHex();
            $value[$foreignKey->getPropertyName()] = $id;
        }

        $this->writeExtractor->extract(
            $value,
            $parameters->cloneForSubresource(
                $referenceClass,
                $parameters->getPath() . '/' . $data->getKey()
            )
        );

        return $id;
    }
}
