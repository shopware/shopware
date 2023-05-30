<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Schema\DynamicEntityDefinition;

/**
 * @internal
 */
#[Package('core')]
class EntitySchemaGenerator implements ApiDefinitionGeneratorInterface
{
    final public const FORMAT = 'entity-schema';

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT;
    }

    public function generate(array $definitions, string $api, string $apiType = 'jsonapi'): never
    {
        throw new \RuntimeException();
    }

    /**
     * @return array<
     *     string,
     *     array{
     *          entity: string,
     *          properties: array<string, array{type: string, flags: array<string, mixed>}>,
     *          write-protected: bool,
     *          read-protected: bool,
     *          flags?: list<Flag>
     *      }
     * >
     */
    public function getSchema(array $definitions): array
    {
        $schema = [];

        ksort($definitions);

        foreach ($definitions as $definition) {
            $entity = $definition->getEntityName();

            $entitySchema = $this->getEntitySchema($definition);

            if ($entitySchema['write-protected'] && $entitySchema['read-protected']) {
                continue;
            }

            $schema[$entity] = $entitySchema;
        }

        return $schema;
    }

    /**
     * @return array{
     *     entity: string,
     *     properties: array<string, array{type: string, flags: array<string, mixed>}>,
     *     write-protected: bool,
     *     read-protected: bool,
     *     flags?: list<Flag>
     *  }
     */
    private function getEntitySchema(EntityDefinition $definition): array
    {
        $fields = $definition->getFields();

        $properties = [];
        foreach ($fields as $field) {
            $properties[$field->getPropertyName()] = $this->parseField($definition, $field);
        }

        $result = [
            'entity' => $definition->getEntityName(),
            'properties' => $properties,
            'write-protected' => $definition->getProtections()->get(WriteProtection::class) !== null,
            'read-protected' => $definition->getProtections()->get(ReadProtection::class) !== null,
        ];

        if ($definition instanceof DynamicEntityDefinition) {
            $result['flags'] = $definition->getFlags();
        }

        return $result;
    }

    /**
     * @return array{type: string, flags: array<string, mixed>}
     */
    private function parseField(EntityDefinition $definition, Field $field): array
    {
        /** @var array<string, mixed> $flags */
        $flags = [];
        foreach ($field->getFlags() as $flag) {
            $flags = array_replace_recursive($flags, iterator_to_array($flag->parse()));
        }

        switch (true) {
            case $field instanceof TranslatedField:
                $property = $this->parseField(
                    $definition,
                    EntityDefinitionQueryHelper::getTranslatedField($definition, $field)
                );
                $property['flags'] = array_replace_recursive($property['flags'], $flags);
                $property['flags']['translatable'] = true;

                return $property;

                // fields with uuid
            case $field instanceof VersionField:
            case $field instanceof ReferenceVersionField:
            case $field instanceof ParentFkField:
            case $field instanceof FkField:
            case $field instanceof IdField:
                return ['type' => 'uuid', 'flags' => $flags];

                // json fields
            case $field instanceof CustomFields:
            case $field instanceof VersionDataPayloadField:
            case $field instanceof CalculatedPriceField:
            case $field instanceof CartPriceField:
            case $field instanceof PriceDefinitionField:
            case $field instanceof PriceField:
            case $field instanceof ObjectField:
                return $this->createJsonObjectType($definition, $field, $flags);

            case $field instanceof ListField:
            case $field instanceof BreadcrumbField:
                return ['type' => 'json_list', 'flags' => $flags];

            case $field instanceof JsonField:
                return $this->createJsonObjectType($definition, $field, $flags);

                // association fields
            case $field instanceof OneToManyAssociationField:
            case $field instanceof ChildrenAssociationField:
            case $field instanceof TranslationsAssociationField:
                if (!$field instanceof OneToManyAssociationField) {
                    throw new \RuntimeException('Field should extend OneToManyAssociationField');
                }

                $reference = $field->getReferenceDefinition();
                $localField = $definition->getFields()->getByStorageName($field->getLocalField());
                $referenceField = $reference->getFields()->getByStorageName($field->getReferenceField());

                $primary = $reference->getPrimaryKeys()->first();
                if (!$primary) {
                    throw new \RuntimeException(sprintf('No primary key defined for %s', $reference->getEntityName()));
                }

                return [
                    'type' => 'association',
                    'relation' => 'one_to_many',
                    'entity' => $reference->getEntityName(),
                    'flags' => $flags,
                    'localField' => $localField ? $localField->getPropertyName() : null,
                    'referenceField' => $referenceField ? $referenceField->getPropertyName() : null,
                    'primary' => $primary->getPropertyName(),
                ];

            case $field instanceof ParentAssociationField:
            case $field instanceof ManyToOneAssociationField:
                if (!$field instanceof AssociationField) {
                    throw new \RuntimeException('Field should extend AssociationField');
                }

                $reference = $field->getReferenceDefinition();
                $localField = $definition->getFields()->getByStorageName($field->getStorageName());
                $referenceField = $reference->getFields()->getByStorageName($field->getReferenceField());

                return [
                    'type' => 'association',
                    'relation' => 'many_to_one',
                    'entity' => $reference->getEntityName(),
                    'flags' => $flags,
                    'localField' => $localField ? $localField->getPropertyName() : null,
                    'referenceField' => $referenceField ? $referenceField->getPropertyName() : null,
                ];

            case $field instanceof ManyToManyAssociationField:
                $reference = $field->getToManyReferenceDefinition();
                $localField = $definition->getFields()->getByStorageName($field->getLocalField());
                $referenceField = $reference->getFields()->getByStorageName($field->getReferenceField());

                $mappingReference = $field->getMappingDefinition()->getFields()->getByStorageName(
                    $field->getMappingReferenceColumn()
                );
                $mappingLocal = $field->getMappingDefinition()->getFields()->getByStorageName(
                    $field->getMappingLocalColumn()
                );

                if (!$mappingReference) {
                    throw new \RuntimeException(sprintf('Can not find mapping entity field for storage field %s', $field->getMappingReferenceColumn()));
                }
                if (!$mappingLocal) {
                    throw new \RuntimeException(sprintf('Can not find mapping entity field for storage field %s', $field->getMappingLocalColumn()));
                }

                return [
                    'type' => 'association',
                    'relation' => 'many_to_many',
                    'local' => $mappingLocal->getPropertyName(),
                    'reference' => $mappingReference->getPropertyName(),
                    'mapping' => $field->getMappingDefinition()->getEntityName(),
                    'entity' => $field->getToManyReferenceDefinition()->getEntityName(),
                    'flags' => $flags,
                    'localField' => $localField ? $localField->getPropertyName() : null,
                    'referenceField' => $referenceField ? $referenceField->getPropertyName() : null,
                ];

            case $field instanceof OneToOneAssociationField:
                $reference = $field->getReferenceDefinition();

                $localField = $definition->getFields()->getByStorageName($field->getStorageName());
                $referenceField = $reference->getFields()->getByStorageName($field->getReferenceField());

                return [
                    'type' => 'association',
                    'relation' => 'one_to_one',
                    'entity' => $reference->getEntityName(),
                    'flags' => $flags,
                    'localField' => $localField ? $localField->getPropertyName() : null,
                    'referenceField' => $referenceField ? $referenceField->getPropertyName() : null,
                ];

                // int fields
            case $field instanceof ChildCountField:
            case $field instanceof TreeLevelField:
            case $field instanceof IntField:
                return ['type' => 'int', 'flags' => $flags];

                // long text fields
            case $field instanceof TreePathField:
            case $field instanceof LongTextField:
                return ['type' => 'text', 'flags' => $flags];

                // date fields
            case $field instanceof UpdatedAtField:
            case $field instanceof CreatedAtField:
            case $field instanceof DateTimeField:
            case $field instanceof DateField:
                return ['type' => 'date', 'flags' => $flags];

                // scalar fields
            case $field instanceof PasswordField:
                return ['type' => 'password', 'flags' => $flags];

            case $field instanceof FloatField:
                return ['type' => 'float', 'flags' => $flags];

            case $field instanceof StringField:
                return ['type' => 'string', 'flags' => $flags];

            case $field instanceof BlobField:
                return ['type' => 'blob', 'flags' => $flags];

            case $field instanceof BoolField:
                return ['type' => 'boolean', 'flags' => $flags];

            default:
                return ['type' => $field::class, 'flags' => $flags];
        }
    }

    /**
     * @param array<string, mixed> $flags
     *
     * @return array{
     *     type: string,
     *     properties: array<string,
     *     array{type: string, flags: array<string, mixed>}>,
     *     flags: array<string, mixed>
     * }
     */
    private function createJsonObjectType(EntityDefinition $definition, Field $field, array $flags): array
    {
        $nested = [];
        if ($field instanceof JsonField) {
            foreach ($field->getPropertyMapping() as $nestedField) {
                $nested[$nestedField->getPropertyName()] = $this->parseField($definition, $nestedField);
            }
        }

        return [
            'type' => 'json_object',
            'properties' => $nested,
            'flags' => $flags,
        ];
    }
}
