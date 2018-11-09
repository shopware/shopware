<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ManyToManyAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtrator;

    public function __construct(WriteCommandExtractor $writeExtrator)
    {
        $this->writeExtrator = $writeExtrator;
    }

    public function getFieldClass(): string
    {
        return ManyToManyAssociationField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ManyToManyAssociationField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', ManyToManyAssociationField::class, \get_class($field))
            );
        }
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
        }

        /** @var ManyToManyAssociationField $field */
        $mappingAssociation = $this->getMappingAssociation($field);

        foreach ($value as $keyValue => $subresources) {
            $mapped = $subresources;
            if ($mappingAssociation) {
                $mapped = $this->map($field, $mappingAssociation, $subresources);
            }

            if (!\is_array($mapped)) {
                throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
            }

            $this->writeExtrator->extract(
                $mapped,
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

    protected function getMappingAssociation(ManyToManyAssociationField $field): ?ManyToOneAssociationField
    {
        $associations = $field->getReferenceClass()::getFields()->filterInstance(ManyToOneAssociationField::class);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            if ($association->getStorageName() === $field->getMappingReferenceColumn()) {
                return $association;
            }
        }

        return null;
    }

    protected function map(ManyToManyAssociationField $field, ManyToOneAssociationField $association, $data): array
    {
        // not only foreign key provided? data is provided as insert or update command
        if (\count($data) > 1) {
            return [$association->getPropertyName() => $data];
        }

        // no id provided? data is provided as insert command (like create category in same request with the product)
        if (!isset($data[$association->getReferenceField()])) {
            return [$association->getPropertyName() => $data];
        }

        //only foreign key provided? entity should only be linked
        /*e.g
            [
                categories => [
                    ['id' => {id}],
                    ['id' => {id}]
                ]
            ]
        */
        /** @var ManyToOneAssociationField $association */
        $fk = $field->getReferenceClass()::getFields()->getByStorageName(
            $association->getStorageName()
        );

        if (!$fk) {
            trigger_error(sprintf('Foreign key for association %s not found', $association->getPropertyName()));

            return [$association->getPropertyName() => $data];
        }

        /* @var FkField $fk */
        return [$fk->getPropertyName() => $data[$association->getReferenceField()]];
    }
}
