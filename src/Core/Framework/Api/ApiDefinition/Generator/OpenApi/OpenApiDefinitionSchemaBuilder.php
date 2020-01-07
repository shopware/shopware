<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Uuid\Uuid;

class OpenApiDefinitionSchemaBuilder
{
    /**
     * @var ApiVersionConverter
     */
    private $converter;

    public function __construct(ApiVersionConverter $converter)
    {
        $this->converter = $converter;
    }

    /**
     * @return Schema[]
     */
    public function getSchemaByDefinition(EntityDefinition $definition, string $path, bool $forSalesChannel, int $version, bool $onlyFlat = false): array
    {
        $attributes = [];
        $requiredAttributes = [];
        $relationships = [];

        $uuid = Uuid::randomHex();
        $schemaName = $definition->getEntityName();
        $detailPath = $path . '/' . $uuid;

        $extensions = [];
        $extensionRelationships = [];

        /** @var Field $field */
        foreach ($definition->getFields() as $field) {
            if (!$this->shouldFieldBeIncluded($definition, $field, $forSalesChannel, $version)) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $extensions[] = $field;

                continue;
            }

            if ($field->is(Required::class) && !$field instanceof VersionField && !$field instanceof ReferenceVersionField) {
                $requiredAttributes[] = $field->getPropertyName();
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToOneLinkage($field, $detailPath);

                continue;
            }

            if ($field instanceof AssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToManyLinkage($field, $detailPath);

                continue;
            }

            if ($field instanceof TranslatedField && $definition->getTranslationDefinition()) {
                $field = $definition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            }

            if ($field instanceof JsonField) {
                $attributes[$field->getPropertyName()] = $this->resolveJsonField($field, $version);

                continue;
            }

            $attr = $this->getPropertyByField(\get_class($field), $field->getPropertyName());

            if (\in_array($field->getPropertyName(), ['createdAt', 'updatedAt'], true) || $this->isWriteProtected($field)) {
                $attr->readOnly = true;
            }

            if ($this->isDeprecated($field, $version)) {
                $attr->deprecated = true;
            }

            $attributes[$field->getPropertyName()] = $attr;
        }

        $extensionAttributes = $this->getExtensions($extensions, $detailPath, $version);

        if (!empty($extensionAttributes)) {
            $attributes['extensions'] = new Property([
                'type' => 'object',
                'property' => 'extensions',
                'properties' => $extensionAttributes,
            ]);

            foreach ($extensions as $extension) {
                if (!$extension instanceof AssociationField) {
                    continue;
                }

                $extensionRelationships[$extension->getPropertyName()] = $extensionAttributes[$extension->getPropertyName()];
            }
        }

        if ($definition->getTranslationDefinition()) {
            foreach ($definition->getTranslationDefinition()->getFields() as $field) {
                if ($field->getPropertyName() === 'translations' || $field->getPropertyName() === 'id') {
                    continue;
                }

                if ($field->is(Required::class) && !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof FkField) {
                    $requiredAttributes[] = $field->getPropertyName();
                }
            }
        }

        if (!$onlyFlat) {
            /* @var Schema[] $schema */
            $schema[$schemaName] = new Schema([
                'schema' => $schemaName,
                'allOf' => [
                    new Schema(['ref' => '#/components/schemas/resource']),
                    new Schema([
                        'type' => 'object',
                        'schema' => $schemaName,
                        'properties' => [
                            'type' => ['example' => $definition->getEntityName()],
                            'id' => ['example' => $uuid],
                            'attributes' => [
                                'type' => 'object',
                                'required' => array_unique($requiredAttributes),
                                'properties' => $attributes,
                            ],
                            'links' => [
                                'properties' => [
                                    'self' => [
                                        'type' => 'string',
                                        'format' => 'uri-reference',
                                        'example' => $detailPath,
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ],
            ]);

            if (\count($relationships)) {
                $schema[$schemaName]->allOf[1]->properties['relationships'] = new Property([
                    'property' => 'relationships',
                    'properties' => $relationships,
                ]);
            }
        }

        $attributes = array_merge(['id' => new Property(['type' => 'string', 'property' => 'id', 'format' => 'uuid'])], $attributes);

        foreach ($relationships as $property => $relationship) {
            $entity = $this->getRelationShipEntity($relationship);
            $attributes[$property] = new Property(['ref' => '#/components/schemas/' . $entity . '_flat', 'property' => $property]);
        }

        if (!empty($extensionRelationships)) {
            $attributes['extensions'] = clone $attributes['extensions'];
            foreach ($extensionRelationships as $property => $relationship) {
                $entity = $this->getRelationShipEntity($relationship);
                $attributes['extensions']->properties[$property] = new Property(['ref' => '#/components/schemas/' . $entity . '_flat', 'property' => $property]);
            }
        }

        $schema[$schemaName . '_flat'] = new Schema([
            'type' => 'object',
            'schema' => $schemaName . '_flat',
            'properties' => $attributes,
            'required' => array_unique($requiredAttributes),
        ]);

        return $schema;
    }

    private function shouldFieldBeIncluded(EntityDefinition $definition, Field $field, bool $forSalesChannel, int $version): bool
    {
        if ($field->getPropertyName() === 'translations'
            || $field->getPropertyName() === 'id'
            || preg_match('#translations$#i', $field->getPropertyName())) {
            return false;
        }

        /** @var ReadProtected|null $readProtected */
        $readProtected = $field->getFlag(ReadProtected::class);
        if ($readProtected && !$readProtected->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class)) {
            return false;
        }

        if (!$this->converter->isAllowed($definition->getEntityName(), $field->getPropertyName(), $version)) {
            return false;
        }

        return true;
    }

    /**
     * @param ManyToOneAssociationField|OneToOneAssociationField $field
     */
    private function createToOneLinkage($field, string $basePath): Property
    {
        return new Property([
            'type' => 'object',
            'property' => $field->getPropertyName(),
            'properties' => [
                'links' => [
                    'type' => 'object',
                    'property' => 'links',
                    'properties' => [
                        'related' => [
                            'type' => 'string',
                            'format' => 'uri-reference',
                            'example' => $basePath . '/' . $field->getPropertyName(),
                        ],
                    ],
                ],
                'data' => [
                    'type' => 'object',
                    'property' => 'data',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'example' => $field->getReferenceDefinition()->getEntityName(),
                        ],
                        'id' => [
                            'type' => 'string',
                            'format' => 'uuid',
                            'example' => Uuid::randomHex(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param ManyToManyAssociationField|OneToManyAssociationField|AssociationField $field
     */
    private function createToManyLinkage(AssociationField $field, string $basePath): Property
    {
        $associationEntityName = $field->getReferenceDefinition()->getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $associationEntityName = $field->getToManyReferenceDefinition()->getEntityName();
        }

        return new Property([
            'type' => 'object',
            'property' => $field->getPropertyName(),
            'properties' => [
                'links' => [
                    'property' => 'links',
                    'type' => 'object',
                    'properties' => [
                        'related' => [
                            'type' => 'string',
                            'format' => 'uri-reference',
                            'example' => $basePath . '/' . $field->getPropertyName(),
                        ],
                    ],
                ],
                'data' => [
                    'type' => 'array',
                    'property' => 'data',
                    'items' => [
                        'type' => 'object',
                        'property' => 'items',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'property' => 'type',
                                'example' => $associationEntityName,
                            ],
                            'id' => [
                                'type' => 'string',
                                'property' => 'id',
                                'example' => Uuid::randomHex(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param Field[] $extensions
     *
     * @return Property[]
     */
    private function getExtensions(array $extensions, string $path, int $version): array
    {
        $attributes = [];
        foreach ($extensions as $field) {
            $property = $field->getPropertyName();

            $schema = null;
            if ($field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField) {
                $schema = $this->createToManyLinkage($field, $path);
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $schema = $this->createToOneLinkage($field, $path);
            }

            if ($field instanceof JsonField) {
                $schema = $this->resolveJsonField($field, $version);
            }

            if ($schema === null) {
                continue;
            }

            if ($this->isWriteProtected($field)) {
                $schema->readOnly = true;
            }

            if ($this->isDeprecated($field, $version)) {
                $schema->deprecated = true;
            }

            $attributes[$property] = $schema;
        }

        return $attributes;
    }

    private function resolveJsonField(JsonField $jsonField, int $version): Property
    {
        if ($jsonField instanceof ListField) {
            $definition = new Property([
                'type' => 'array',
                'property' => $jsonField->getPropertyName(),
                'items' => $jsonField->getFieldType() ? $this->getPropertyByField($jsonField->getFieldType(), $jsonField->getPropertyName()) : [],
            ]);
        } else {
            $definition = new Property([
                'type' => 'object',
                'property' => $jsonField->getPropertyName(),
            ]);
        }

        $required = [];

        if (!empty($jsonField->getPropertyMapping())) {
            $definition->properties = [];
        }

        foreach ($jsonField->getPropertyMapping() as $field) {
            if ($field instanceof JsonField) {
                $definition->properties[$field->getPropertyName()] = $this->resolveJsonField($field, $version);

                continue;
            }

            if ($field->is(Required::class)) {
                $required[] = $field->getPropertyName();
            }

            $definition->properties[$field->getPropertyName()] = $this->getPropertyByField(\get_class($field), $field->getPropertyName());
        }

        if (\count($required)) {
            $definition->required = $required;
        }
        if ($this->isWriteProtected($jsonField)) {
            $definition->readOnly = true;
        }

        if ($this->isDeprecated($jsonField, $version)) {
            $definition->deprecated = true;
        }

        return $definition;
    }

    private function getPropertyByField(string $fieldClass, string $propertyName): Property
    {
        $property = new Property([
            'type' => $this->getType($fieldClass),
            'property' => $propertyName,
        ]);

        if (\is_a($fieldClass, DateTimeField::class, true)) {
            $property->format = 'date-time';
        }
        if (\is_a($fieldClass, FloatField::class, true)) {
            $property->format = 'float';
        }
        if (\is_a($fieldClass, IntField::class, true)) {
            $property->format = 'int64';
        }
        if (\is_a($fieldClass, IdField::class, true) || \is_a($fieldClass, FkField::class, true)) {
            $property->type = 'string';
            $property->format = 'uuid';
        }

        return $property;
    }

    private function getType(string $fieldClass): string
    {
        if (\is_a($fieldClass, FloatField::class, true)) {
            return 'number';
        }
        if (\is_a($fieldClass, IntField::class, true)) {
            return 'integer';
        }
        if (\is_a($fieldClass, BoolField::class, true)) {
            return 'boolean';
        }
        if (\is_a($fieldClass, ListField::class, true)) {
            return 'array';
        }
        if (\is_a($fieldClass, JsonField::class, true)) {
            return 'object';
        }

        return 'string';
    }

    private function isWriteProtected(Field $field): bool
    {
        /** @var WriteProtected|null $writeProtection */
        $writeProtection = $field->getFlag(WriteProtected::class);
        if ($writeProtection && !$writeProtection->isAllowed(Context::USER_SCOPE)) {
            return true;
        }

        return false;
    }

    private function isDeprecated(Field $field, int $version): bool
    {
        /** @var Deprecated|null $deprecated */
        $deprecated = $field->getFlag(Deprecated::class);
        if ($deprecated && $deprecated->isDeprecatedInVersion($version)) {
            return true;
        }

        return false;
    }

    private function getRelationShipEntity(Property $relationship): string
    {
        /** @var array $relationshipData */
        $relationshipData = $relationship->properties['data'];
        $type = $relationshipData['type'];
        $entity = '';

        if ($type === 'object') {
            $entity = $relationshipData['properties']['type']['example'];
        } elseif ($type === 'array') {
            $entity = $relationshipData['items']['properties']['type']['example'];
        }

        return $entity;
    }
}
