<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ChildrenAssociationFieldSerializer implements FieldSerializerInterface
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
        return ChildrenAssociationField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ChildrenAssociationField) {
            throw new InvalidSerializerFieldException(ChildrenAssociationField::class, $field);
        }

        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $data->getKey(), 'Value must be an array.');
        }

        $this->map($field, $parameters, $data);

        return;
        yield __CLASS__ => __METHOD__;
    }

    public function decode(Field $field, $value)
    {
        throw new DecodeByHydratorException($field);
    }

    private function map(ChildrenAssociationField $field, WriteParameterBag $parameters, KeyValuePair $data): void
    {
        $parentId = $parameters->getContext()->get($field->getReferenceClass(), 'id');

        foreach ($data->getValue() as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($parameters->getPath() . '/' . $data->getKey(), 'Value must be an array.');
            }

            $subresources['parentId'] = $parentId;

            $this->writeExtractor->extract(
                $subresources,
                $parameters->cloneForSubresource(
                    $field->getReferenceClass(),
                    $parameters->getPath() . '/' . $data->getKey() . '/' . $keyValue
                )
            );
        }
    }
}
