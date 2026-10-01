<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Schema;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Builds the component schemas of an entity definition.
 *
 * Schema keys follow the order the OpenAPI schema object lists them in (description, required, properties, type,
 * format, items, pattern, readOnly, deprecated, enum), so the generated documents stay stable.
 *
 * @phpstan-type OpenApiSchema array<string, mixed>
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class OpenApiDefinitionSchemaBuilder
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    /**
     * @internal
     *
     * @param iterable<FieldEnumProviderInterface> $enumProviders
     */
    public function __construct(private readonly iterable $enumProviders = [])
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter(null, false);
    }

    /**
     * Builds the flat schema of the definition and, for the JSON:API type, the resource schema wrapping it.
     *
     * @internal
     *
     * @return array<string, OpenApiSchema> schemas keyed by their component name
     */
    public function createSchemas(
        EntityDefinition $definition,
        string $path,
        bool $forSalesChannel,
        bool $onlyFlat = false,
        string $apiType = DefinitionService::TYPE_JSON_API
    ): array {
        $schemas = [];
        $attributes = [];
        $requiredProperties = [];
        $requiredAttributes = [];
        $relationships = [];
        $relationshipAttributes = [];

        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());
        $uuid = Uuid::fromStringToHex($schemaName);
        $exampleDetailPath = $path . '/' . $uuid;

        $extensions = [];

        $defaults = $definition->getDefaults();

        foreach ($definition->getFields() as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel)) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $extensions[] = $field;

                continue;
            }

            $isRequired = (
                $field->is(Required::class)
                && !$field instanceof VersionField
                && !$field instanceof ReferenceVersionField
                && !$field instanceof CreatedAtField
                && !$field instanceof UpdatedAtField
                && !\array_key_exists($field->getPropertyName(), $defaults)
            );

            if ($isRequired) {
                $requiredProperties[] = $field->getPropertyName();
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToOneLinkage($field, $exampleDetailPath);
                $relationshipAttributes[$field->getPropertyName()] = $this->createRelationShipProperty($field);

                continue;
            }

            if ($field instanceof AssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToManyLinkage($field, $exampleDetailPath);
                $relationshipAttributes[$field->getPropertyName()] = $this->createRelationShipProperty($field);

                continue;
            }

            if ($field instanceof TranslatedField && $definition->getTranslationDefinition()) {
                $field = $definition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            }

            if ($field === null) {
                continue;
            }

            if ($isRequired) {
                $requiredAttributes[] = $field->getPropertyName();
            }

            if ($field instanceof JsonField) {
                $attributes[$field->getPropertyName()] = $this->resolveJsonField($field);

                continue;
            }

            $attribute = $this->getPropertyByField($field);

            if (\in_array($field->getPropertyName(), ['createdAt', 'updatedAt'], true) || $this->isWriteProtected($field)) {
                $attribute['readOnly'] = true;
            }

            if ($this->isDeprecated($field)) {
                $attribute['deprecated'] = true;
            }

            $enumValues = [];
            $choice = $field->getFlag(Choice::class);
            if ($choice instanceof Choice) {
                $enumValues = $choice->getChoices();
            }

            foreach ($this->enumProviders as $enumProvider) {
                if (!$enumProvider->isSupported($definition->getEntityName(), $field->getPropertyName())) {
                    continue;
                }

                $enumValues = array_merge($enumValues, $enumProvider->getChoices());
            }

            $enumValues = array_values(array_unique($enumValues, \SORT_REGULAR));

            if ($enumValues !== [] && \in_array($attribute['type'], ['string', 'integer', 'number', 'boolean'], true)) {
                $attribute['enum'] = $enumValues;
            }

            $attributes[$field->getPropertyName()] = $attribute;
        }

        $extensionAttributes = $this->getExtensions($extensions, $exampleDetailPath);

        if ($extensionAttributes !== []) {
            $attributes['extensions'] = [
                'properties' => $extensionAttributes,
                'type' => 'object',
            ];
        }

        if ($definition->getTranslationDefinition()) {
            foreach ($definition->getTranslationDefinition()->getFields() as $field) {
                $propertyName = $field->getPropertyName();
                if (\in_array($propertyName, ['translations', 'id'], true)) {
                    continue;
                }

                if (
                    $field->is(Required::class)
                    && !$field instanceof VersionField
                    && !$field instanceof ReferenceVersionField
                    && !$field instanceof CreatedAtField
                    && !$field instanceof UpdatedAtField
                    && !$field instanceof FkField) {
                    $requiredProperties[] = $propertyName;
                    $requiredAttributes[] = $propertyName;
                }
            }
        }

        $attributes = ['id' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$']] + $attributes;
        $requiredAttributes = array_values(array_unique($requiredAttributes));
        $requiredProperties = array_values(array_unique($requiredProperties));

        $since = $definition->since();
        if (!$onlyFlat && $apiType === DefinitionService::TYPE_JSON_API) {
            $resourceSchema = [];

            $requiredAttributes = $this->filterRequiredProperties($requiredAttributes, $attributes);
            if ($requiredAttributes !== []) {
                $resourceSchema['required'] = $requiredAttributes;
            }

            $resourceSchema['properties'] = $attributes;
            if ($relationships !== []) {
                $resourceSchema['properties']['relationships'] = [
                    'properties' => $relationships,
                    'type' => 'object',
                ];
            }

            $resourceSchema['type'] = 'object';

            $jsonApiSchema = [];
            if ($since !== null && $since !== '') {
                $jsonApiSchema['description'] = 'Added since version: ' . $since;
            }

            $jsonApiSchema['allOf'] = [
                ['$ref' => '#/components/schemas/resource'],
                $resourceSchema,
            ];

            $schemas[$schemaName . 'JsonApi'] = $jsonApiSchema;
        }

        $attributes += $relationshipAttributes;

        $requiredProperties = $this->filterRequiredProperties($requiredProperties, $attributes);

        // In some entities all fields are hidden, but not the id. This creates unwanted schemas. This removes it again
        if (array_keys($attributes) === ['id']) {
            return [];
        }

        $flatSchema = [];
        if ($since !== null && $since !== '') {
            $flatSchema['description'] = 'Added since version: ' . $since;
        }

        if ($requiredProperties !== []) {
            $flatSchema['required'] = $requiredProperties;
        }

        $flatSchema['properties'] = $attributes;
        $flatSchema['type'] = 'object';

        $schemas[$schemaName] = $flatSchema;

        return $schemas;
    }

    public function getSchemaName(EntityDefinition $definition): string
    {
        return $this->snakeCaseToCamelCase($definition->getEntityName());
    }

    /**
     * Builds only the dynamic entity-extension contribution for a JSON-owned component.
     *
     * @internal
     *
     * @return array<string, OpenApiSchema> the schema keyed by its component name, empty without extensions
     */
    public function createExtensionSchemas(EntityDefinition $definition, string $path, bool $forSalesChannel): array
    {
        $schemaName = $this->getSchemaName($definition);
        $exampleDetailPath = $path . '/' . Uuid::fromStringToHex($schemaName);
        $extensions = [];

        foreach ($definition->getFields() as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel) || !$field->is(Extension::class)) {
                continue;
            }

            $extensions[] = $field;
        }

        $properties = $this->getExtensions($extensions, $exampleDetailPath);

        if ($properties === []) {
            return [];
        }

        return [
            $schemaName => [
                'properties' => [
                    'extensions' => [
                        'properties' => $properties,
                        'type' => 'object',
                    ],
                ],
                'type' => 'object',
            ],
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, the class becomes internal
     *
     * @return array<string, Schema>
     */
    public function getSchemaByDefinition(
        EntityDefinition $definition,
        string $path,
        bool $forSalesChannel,
        bool $onlyFlat = false,
        string $apiType = DefinitionService::TYPE_JSON_API
    ): array {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->createSchemaAnnotations($this->createSchemas($definition, $path, $forSalesChannel, $onlyFlat, $apiType));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, the class becomes internal
     *
     * @return array<string, Schema>
     */
    public function getExtensionSchemaByDefinition(EntityDefinition $definition, string $path, bool $forSalesChannel): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->createSchemaAnnotations($this->createExtensionSchemas($definition, $path, $forSalesChannel));
    }

    /**
     * @param array<string, OpenApiSchema> $schemas
     *
     * @return array<string, Schema>
     */
    private function createSchemaAnnotations(array $schemas): array
    {
        $annotations = [];

        foreach ($schemas as $schemaName => $schema) {
            $annotations[$schemaName] = new Schema(['schema' => $schemaName] + $schema);
        }

        return $annotations;
    }

    /**
     * @param list<string> $requiredProperties
     * @param array<string, OpenApiSchema> $properties
     *
     * @return list<string>
     */
    private function filterRequiredProperties(array $requiredProperties, array $properties): array
    {
        return array_values(array_filter(
            $requiredProperties,
            static fn (string $requiredProperty): bool => isset($properties[$requiredProperty])
        ));
    }

    private function snakeCaseToCamelCase(string $input): string
    {
        return $this->converter->denormalize($input);
    }

    private function shouldFieldBeIncluded(Field $field, bool $forSalesChannel): bool
    {
        if ($field->getPropertyName() === 'translations'
            || preg_match('#translations$#i', $field->getPropertyName())
        ) {
            return false;
        }

        $ignoreOpenApiSchemaFlag = $field->getFlag(IgnoreInOpenapiSchema::class);
        if ($ignoreOpenApiSchemaFlag !== null) {
            return false;
        }

        $flag = $field->getFlag(ApiAware::class);
        if ($flag === null) {
            return false;
        }

        return $flag->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class);
    }

    /**
     * @return OpenApiSchema
     */
    private function createToOneLinkage(ManyToOneAssociationField|OneToOneAssociationField $field, string $basePath): array
    {
        $property = [];

        if ($field->getDescription() !== '') {
            $property['description'] = $field->getDescription();
        }

        $property['properties'] = [
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
                        'example' => Uuid::fromStringToHex($field->getPropertyName()),
                    ],
                ],
            ],
        ];
        $property['type'] = 'object';

        return $property;
    }

    /**
     * @return OpenApiSchema
     */
    private function createToManyLinkage(AssociationField $field, string $basePath): array
    {
        $associationEntityName = $field->getReferenceDefinition()->getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $associationEntityName = $field->getToManyReferenceDefinition()->getEntityName();
        }

        $property = [];

        if ($field->getDescription() !== '') {
            $property['description'] = $field->getDescription();
        }

        $property['properties'] = [
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
                            'example' => Uuid::fromStringToHex($field->getPropertyName()),
                        ],
                    ],
                ],
            ],
        ];
        $property['type'] = 'object';

        return $property;
    }

    /**
     * @param Field[] $extensions
     *
     * @return array<string, OpenApiSchema> schemas keyed by the extension's property name
     */
    private function getExtensions(array $extensions, string $path): array
    {
        $attributes = [];
        foreach ($extensions as $field) {
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

            if ($schema === null && !$field instanceof AssociationField) {
                $schema = $this->getPropertyByField($field);
            }

            if ($schema === null) {
                continue;
            }

            if ($this->isWriteProtected($field)) {
                $schema['readOnly'] = true;
            }

            if ($this->isDeprecated($field)) {
                $schema['deprecated'] = true;
            }

            $attributes[$field->getPropertyName()] = $schema;
        }

        return $attributes;
    }

    /**
     * @return OpenApiSchema
     */
    private function resolveJsonField(JsonField $jsonField): array
    {
        if ($jsonField instanceof MeasurementUnitsField) {
            return ['$ref' => '#/components/schemas/MeasurementUnits'];
        }

        $required = [];
        $properties = [];

        foreach ($jsonField->getPropertyMapping() as $field) {
            if ($field instanceof JsonField) {
                $properties[$field->getPropertyName()] = $this->resolveJsonField($field);

                continue;
            }

            if ($field->is(Required::class)) {
                $required[] = $field->getPropertyName();
            }

            $properties[$field->getPropertyName()] = $this->getPropertyByField($field);
        }

        $definition = [];

        if ($required !== []) {
            $definition['required'] = $required;
        }

        if ($properties !== []) {
            $definition['properties'] = $properties;
        }

        if ($jsonField instanceof ListField || $jsonField instanceof BreadcrumbField) {
            $definition['type'] = 'array';
            $definition['items'] = $this->getPropertyAssociationsByField($jsonField instanceof ListField ? $jsonField->getFieldType() : null);
        } elseif ($jsonField instanceof PriceField) {
            $definition['type'] = 'array';
            $definition['items'] = ['$ref' => '#/components/schemas/Price'];
        } else {
            $definition['type'] = 'object';
        }

        if ($this->isWriteProtected($jsonField)) {
            $definition['readOnly'] = true;
        }

        if ($this->isDeprecated($jsonField)) {
            $definition['deprecated'] = true;
        }

        return $definition;
    }

    /**
     * @return OpenApiSchema
     */
    private function getPropertyByField(Field $field): array
    {
        $fieldClass = $field::class;

        $property = [];

        $description = [];
        if ($field->getDescription() !== '') {
            $description[] = $field->getDescription();
        }
        $flag = $field->getFlag(Since::class);
        if ($flag instanceof Since) {
            $description[] = \sprintf('Added since version: %s.', $flag->getSince());
        }

        $flag = $field->getFlag(Runtime::class);
        if ($flag instanceof Runtime) {
            $description[] = 'Runtime field, cannot be used as part of the criteria.';
        }

        $description = \implode(' ', $description);
        if ($description !== '') {
            $property['description'] = $description;
        }

        $property['type'] = $this->getType($fieldClass);

        if (is_a($fieldClass, DateTimeField::class, true)) {
            $property['format'] = 'date-time';
        }
        if (is_a($fieldClass, FloatField::class, true)) {
            $property['format'] = 'float';
        }
        if (is_a($fieldClass, IntField::class, true)) {
            $property['format'] = 'int64';
        }
        if (is_a($fieldClass, IdField::class, true) || is_a($fieldClass, FkField::class, true)) {
            $property['type'] = 'string';
            $property['pattern'] = '^[0-9a-f]{32}$';
        }

        return $property;
    }

    /**
     * @return OpenApiSchema
     */
    private function getPropertyAssociationsByField(?string $fieldClass): array
    {
        if ($fieldClass === null) {
            return [
                'type' => 'object',
                'additionalProperties' => false,
            ];
        }

        $property = ['type' => $this->getType($fieldClass)];

        if (is_a($fieldClass, DateTimeField::class, true)) {
            $property['format'] = 'date-time';
        }
        if (is_a($fieldClass, FloatField::class, true)) {
            $property['format'] = 'float';
        }
        if (is_a($fieldClass, IntField::class, true)) {
            $property['format'] = 'int64';
        }
        if (is_a($fieldClass, IdField::class, true) || is_a($fieldClass, FkField::class, true)) {
            $property['type'] = 'string';
            $property['pattern'] = '^[0-9a-f]{32}$';
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
        $writeProtection = $field->getFlag(WriteProtected::class);

        return $writeProtection && !$writeProtection->isAllowed(Context::USER_SCOPE);
    }

    private function isDeprecated(Field $field): bool
    {
        return $field->getFlag(Deprecated::class) !== null;
    }

    /**
     * @return OpenApiSchema
     */
    private function createRelationShipProperty(AssociationField $field): array
    {
        $entity = $field->getReferenceDefinition()->getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $entity = $field->getToManyReferenceDefinition()->getEntityName();
        }

        $reference = '#/components/schemas/' . $this->snakeCaseToCamelCase($entity);

        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
            $property = ['$ref' => $reference];

            if ($field->getDescription() !== '') {
                $property['description'] = $field->getDescription();
            }

            return $property;
        }

        $property = [];

        if ($field->getDescription() !== '') {
            $property['description'] = $field->getDescription();
        }

        $property['type'] = 'array';
        $property['items'] = ['$ref' => $reference];

        return $property;
    }
}
