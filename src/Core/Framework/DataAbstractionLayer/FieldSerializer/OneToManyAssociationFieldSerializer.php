<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class OneToManyAssociationFieldSerializer implements FieldSerializerInterface
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
        return OneToManyAssociationField::class;
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
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $data->getKey(), 'Value must be an array.');
        }

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
            }

            /* @var OneToManyAssociationField $field */
            $this->writeExtractor->extract(
                $subresources,
                $parameters->cloneForSubresource(
                    $field->getReferenceClass(),
                    $parameters->getPath() . '/' . $key . '/' . $keyValue
                )
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }

    public function decode(Field $field, $value)
    {
        throw new DecodeByHydratorException($field);
    }
}
