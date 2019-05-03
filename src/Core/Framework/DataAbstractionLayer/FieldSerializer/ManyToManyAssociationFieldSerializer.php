<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class ManyToManyAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtrator;

    public function __construct(
        WriteCommandExtractor $writeExtrator
    ) {
        $this->writeExtrator = $writeExtrator;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ManyToManyAssociationField) {
            throw new InvalidSerializerFieldException(ManyToManyAssociationField::class, $field);
        }
        $key = $data->getKey();
        $value = $data->getValue();
        $referencedDefinition = $field->getMappingDefinition();

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath() . '/' . $key);
        }

        $mappingAssociation = $this->getMappingAssociation($referencedDefinition, $field);

        foreach ($value as $keyValue => $subresources) {
            $mapped = $subresources;
            if ($mappingAssociation) {
                $mapped = $this->map($referencedDefinition, $field, $mappingAssociation, $subresources);
            }

            if (!\is_array($mapped)) {
                throw new ExpectedArrayException($parameters->getPath() . '/' . $key);
            }

            $this->writeExtrator->extract(
                $mapped,
                $parameters->cloneForSubresource(
                    $referencedDefinition,
                    $parameters->getPath() . '/' . $key . '/' . $keyValue
                )
            );
        }

        yield from [];
    }

    public function decode(Field $field, $value): void
    {
        throw new DecodeByHydratorException($field);
    }

    protected function getMappingAssociation(EntityDefinition $referencedDefinition, ManyToManyAssociationField $field): ?ManyToOneAssociationField
    {
        $associations = $referencedDefinition->getFields()->filterInstance(ManyToOneAssociationField::class);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            if ($association->getStorageName() === $field->getMappingReferenceColumn()) {
                return $association;
            }
        }

        return null;
    }

    protected function map(EntityDefinition $referencedDefinition, ManyToManyAssociationField $field, ManyToOneAssociationField $association, $data): array
    {
        // not only foreign key provided? data is provided as insert or update command
        if (\count($data) > 1) {
            $data['id'] = $data['id'] ?? Uuid::randomHex();
            $data['versionId'] = Defaults::LIVE_VERSION;

            return [$association->getPropertyName() => $data];
        }

        // no id provided? data is provided as insert command (like create category in same request with the product)
        if (!isset($data[$association->getReferenceField()])) {
            $data['id'] = $data['id'] ?? Uuid::randomHex();
            $data['versionId'] = Defaults::LIVE_VERSION;

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
        $fk = $referencedDefinition->getFields()->getByStorageName(
            $association->getStorageName()
        );

        if (!$fk) {
            trigger_error(sprintf('Foreign key for association %s not found', $association->getPropertyName()));

            $data['versionId'] = Defaults::LIVE_VERSION;

            return [$association->getPropertyName() => $data];
        }

        /* @var FkField $fk */
        return [
            $fk->getPropertyName() => $data[$association->getReferenceField()],

            //break versioning at many to many relations
            $referencedDefinition->getEntityName() . '_version_id' => Defaults::LIVE_VERSION,
        ];
    }
}
