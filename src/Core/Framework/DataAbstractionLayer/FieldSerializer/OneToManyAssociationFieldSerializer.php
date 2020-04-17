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

    public function decode(Field $field, $value): void
    {
        throw new DecodeByHydratorException($field);
    }

    private function map(OneToManyAssociationField $field, WriteParameterBag $parameters, KeyValuePair $data): void
    {
        $id = $parameters->getContext()->get($parameters->getDefinition()->getClass(), $field->getLocalField());
        $reference = $field->getReferenceDefinition();

        $fkField = $reference->getFields()->getByStorageName($field->getReferenceField());

        // allows to reset the association for a none cascade delete
        $fk = $fkField->getPropertyName();

        foreach ($data->getValue() as $keyValue => $subresources) {
            $currentId = $id;
            if (!\is_array($subresources)) {
                throw new ExpectedArrayException($parameters->getPath() . '/' . $data->getKey());
            }

            if (array_key_exists($fk, $subresources) && $subresources[$fk] === null) {
                $currentId = null;
            }

            $subresources[$fk] = $currentId;

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
