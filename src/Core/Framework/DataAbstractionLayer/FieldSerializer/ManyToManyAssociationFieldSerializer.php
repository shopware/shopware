<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
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

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof ManyToManyAssociationField) {
            throw new InvalidSerializerFieldException(ManyToManyAssociationField::class, $field);
        }

        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;

        if ($value === null) {
            return $data;
        }

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

            $clonedParams = $parameters->cloneForSubresource(
                $referencedDefinition,
                $parameters->getPath() . '/' . $key . '/' . $keyValue
            );

            $done = [];

            foreach ($mapped as $property => $_) {
                if (\array_key_exists($property, $done)) {
                    continue;
                }
                $f = $referencedDefinition->getFields()->get($property);
                if ($f === null) {
                    continue;
                }
                $mapped = $f->getSerializer()->normalize($f, $mapped, $clonedParams);
                $done[$property] = true;
            }

            foreach ($referencedDefinition->getPrimaryKeys() as $pkField) {
                if (\array_key_exists($pkField->getPropertyName(), $done)) {
                    continue;
                }
                $mapped = $pkField->getSerializer()->normalize($pkField, $mapped, $clonedParams);
                $done[$pkField->getPropertyName()] = true;
            }

            $value[$keyValue] = $mapped;
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
        if (!$field instanceof ManyToManyAssociationField) {
            throw new InvalidSerializerFieldException(ManyToManyAssociationField::class, $field);
        }
        $key = $data->getKey();
        $value = $data->getValue();
        $referencedDefinition = $field->getMappingDefinition();

        if ($value === null) {
            yield from [];

            return;
        }

        if (!\is_array($value)) {
            throw new ExpectedArrayException($parameters->getPath() . '/' . $key);
        }

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new ExpectedArrayException($parameters->getPath() . '/' . $key);
            }

            $this->writeExtrator->extract(
                $subresources,
                $parameters->cloneForSubresource(
                    $referencedDefinition,
                    $parameters->getPath() . '/' . $key . '/' . $keyValue
                )
            );
        }

        yield from [];
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): void
    {
        throw new DecodeByHydratorException($field);
    }

    protected function getMappingAssociation(
        EntityDefinition $referencedDefinition,
        ManyToManyAssociationField $field
    ): ?ManyToOneAssociationField {
        $associations = $referencedDefinition->getFields()->filterInstance(ManyToOneAssociationField::class);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            if ($association->getStorageName() === $field->getMappingReferenceColumn()) {
                return $association;
            }
        }

        return null;
    }

    /**
     * @param array $data
     *
     * @deprecated tag:v6.5.0 The parameter $data will be native typed
     * @deprecated tag:v6.5.0 The unused parameter $field will be removed
     */
    protected function map(EntityDefinition $referencedDefinition, ManyToManyAssociationField $field, ManyToOneAssociationField $association, /*array */$data): array
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
            @trigger_error(sprintf('Foreign key for association %s not found', $association->getPropertyName()));

            $data['versionId'] = Defaults::LIVE_VERSION;

            return [$association->getPropertyName() => $data];
        }

        return [
            $fk->getPropertyName() => $data[$association->getReferenceField()],

            //break versioning at many to many relations
            $referencedDefinition->getEntityName() . '_version_id' => Defaults::LIVE_VERSION,
        ];
    }
}
