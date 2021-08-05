<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
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
     * @return Schema[]
     */
    public function getSchemaByDefinition(
        EntityDefinition $definition,
        string $path,
        bool $forSalesChannel,
        bool $onlyFlat = false,
        string $apiType = DefinitionService::TypeJsonApi
    ): array {
        $attributes = [];
        $requiredAttributes = [];
        $relationships = [];

        $uuid = Uuid::randomHex();
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());
        $exampleDetailPath = $path . '/' . $uuid;

        $extensions = [];
        $extensionRelationships = [];

        foreach ($definition->getFields() as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel)) {
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
                $relationships[] = $this->createToOneLinkage($field, $exampleDetailPath);

                continue;
            }

            if ($field instanceof AssociationField) {
                $relationships[] = $this->createToManyLinkage($field, $exampleDetailPath);

                continue;
            }

            if ($field instanceof TranslatedField && $definition->getTranslationDefinition()) {
                $field = $definition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            }

            if ($field === null) {
                continue;
            }

            if ($field instanceof JsonField) {
                $attributes[] = $this->resolveJsonField($field);

                continue;
            }

            $attr = $this->getPropertyByField($field);

            if (\in_array($field->getPropertyName(), ['createdAt', 'updatedAt'], true) || $this->isWriteProtected($field)) {
                $attr->readOnly = true;
            }

            if ($this->isDeprecated($field)) {
                $attr->deprecated = true;
            }

            $attributes[] = $attr;
        }

        $extensionAttributes = $this->getExtensions($extensions, $exampleDetailPath);

        if (!empty($extensionAttributes)) {
            $attributes['extensions'] = new Property([
                'type' => 'object',
                'properties' => $extensionAttributes,
            ]);

            foreach ($extensions as $extension) {
                if (!$extension instanceof AssociationField) {
                    continue;
                }

                $extensionRelationships[] = $extensionAttributes[$extension->getPropertyName()];
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

        $attributes = array_merge([new Property(['property' => 'id', 'type' => 'string', 'pattern' => '^[0-9a-f]{32}$'])], $attributes);

        if (!$onlyFlat && $apiType === 'jsonapi') {
            $schema[$schemaName . 'JsonApi'] = new Schema([
                'schema' => $schemaName . 'JsonApi',
                'allOf' => [
                    new Schema(['ref' => '#/components/schemas/resource']),
                    new Schema([
                        'type' => 'object',
                        'required' => array_unique($requiredAttributes),
                        'properties' => $attributes,
                    ]),
                ],
                'description' => 'Added since version: ' . $definition->since(),
            ]);

            if (\count($relationships)) {
                $schema[$schemaName . 'JsonApi']->allOf[1]->properties[] = new Property([
                    'property' => 'relationships',
                    'properties' => $relationships,
                ]);
            }
        }

        foreach ($relationships as $relationship) {
            $entity = $this->getRelationShipEntity($relationship);
            $entityName = $this->snakeCaseToCamelCase($entity);
            $attributes[] = new Property(['property' => $relationship->property, 'ref' => '#/components/schemas/' . $entityName]);
        }

        if (!empty($extensionRelationships)) {
            $attributes['extensions'] = clone $attributes['extensions'];
            foreach ($extensionRelationships as $property => $relationship) {
                $entity = $this->getRelationShipEntity($relationship);
                $entityName = $this->snakeCaseToCamelCase($entity);
                $attributes['extensions']->properties[$property] = new Property(['ref' => '#/components/schemas/' . $entityName]);
            }
        }

        // In some entities all fields are hidden, but not the id. This creates unwanted schemas. This removes it again
        if (\count($attributes) === 1 && $attributes[0]->property === 'id') {
            return [];
        }

        $schema[$schemaName] = new Schema([
            'type' => 'object',
            'schema' => $schemaName,
            'properties' => $attributes,
            'required' => array_unique($requiredAttributes),
            'description' => 'Added since version: ' . $definition->since(),
        ]);

        return $schema;
    }

    private function snakeCaseToCamelCase(string $input): string
    {
        return str_replace('_', '', ucwords($input, '_'));
    }

    private function shouldFieldBeIncluded(Field $field, bool $forSalesChannel): bool
    {
        if ($field->getPropertyName() === 'translations'
            || $field->getPropertyName() === 'id'
            || preg_match('#translations$#i', $field->getPropertyName())) {
            return false;
        }

        /** @var ApiAware|null $flag */
        $flag = $field->getFlag(ApiAware::class);
        if ($flag === null) {
            return false;
        }

        if (!$flag->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class)) {
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
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'example' => $field->getReferenceDefinition()->getEntityName(),
                        ],
                        'id' => [
                            'type' => 'string',
                            'pattern' => '^[0-9a-f]{32}$',
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
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'example' => $associationEntityName,
                            ],
                            'id' => [
                                'type' => 'string',
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
    private function getExtensions(array $extensions, string $path): array
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
                $schema = $this->resolveJsonField($field);
            }

            if ($schema === null) {
                continue;
            }

            if ($this->isWriteProtected($field)) {
                $schema->readOnly = true;
            }

            if ($this->isDeprecated($field)) {
                $schema->deprecated = true;
            }

            $attributes[$property] = $schema;
        }

        return $attributes;
    }

    private function resolveJsonField(JsonField $jsonField): Property
    {
        if ($jsonField instanceof ListField) {
            $definition = new Property([
                'type' => 'array',
                'property' => $jsonField->getPropertyName(),
                'items' => $this->getPropertyAssocsByField($jsonField->getFieldType()),
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
                $definition->properties[] = $this->resolveJsonField($field);

                continue;
            }

            if ($field->is(Required::class)) {
                $required[] = $field->getPropertyName();
            }

            $definition->properties[] = $this->getPropertyByField($field);
        }

        if (\count($required)) {
            $definition->required = $required;
        }
        if ($this->isWriteProtected($jsonField)) {
            $definition->readOnly = true;
        }

        if ($this->isDeprecated($jsonField)) {
            $definition->deprecated = true;
        }

        return $definition;
    }

    private function getPropertyByField(Field $field): Property
    {
        $fieldClass = \get_class($field);

        $property = new Property([
            'type' => $this->getType($fieldClass),
            'property' => $field->getPropertyName(),
        ]);

        if (is_a($fieldClass, DateTimeField::class, true)) {
            $property->format = 'date-time';
        }
        if (is_a($fieldClass, FloatField::class, true)) {
            $property->format = 'float';
        }
        if (is_a($fieldClass, IntField::class, true)) {
            $property->format = 'int64';
        }
        if (is_a($fieldClass, IdField::class, true) || is_a($fieldClass, FkField::class, true)) {
            $property->type = 'string';
            $property->pattern = '^[0-9a-f]{32}$';
        }

        $flag = $field->getFlag(Since::class);
        if ($flag instanceof Since) {
            $property->description = 'Added since version: ' . $flag->getSince();
        }

        return $property;
    }

    private function getPropertyAssocsByField(?string $fieldClass): object
    {
        $property = new \stdClass();
        if ($fieldClass === null) {
            $property->additionalProperties = false;

            return $property;
        }

        $property->type = $this->getType($fieldClass);

        if (is_a($fieldClass, DateTimeField::class, true)) {
            $property->format = 'date-time';
        }
        if (is_a($fieldClass, FloatField::class, true)) {
            $property->format = 'float';
        }
        if (is_a($fieldClass, IntField::class, true)) {
            $property->format = 'int64';
        }
        if (is_a($fieldClass, IdField::class, true) || is_a($fieldClass, FkField::class, true)) {
            $property->type = 'string';
            $property->pattern = '^[0-9a-f]{32}$';
        }

        return $property;
    }

    private function getType(string $fieldClass): string
    {
        if (is_a($fieldClass, FloatField::class, true)) {
            return 'number';
        }
        if (is_a($fieldClass, IntField::class, true)) {
            return 'integer';
        }
        if (is_a($fieldClass, BoolField::class, true)) {
            return 'boolean';
        }
        if (is_a($fieldClass, ListField::class, true)) {
            return 'array';
        }
        if (is_a($fieldClass, JsonField::class, true)) {
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

    private function isDeprecated(Field $field): bool
    {
        /** @var Deprecated|null $deprecated */
        $deprecated = $field->getFlag(Deprecated::class);
        if ($deprecated) {
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
