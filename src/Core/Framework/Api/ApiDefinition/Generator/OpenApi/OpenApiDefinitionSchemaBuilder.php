<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Context as OpenApiContext;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class OpenApiDefinitionSchemaBuilder
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    private ?string $lastPostSchemaRef = null;

    /**
     * @var array<string>
     */
    private array $lastPostRequiredFields = [];

    /**
     * @internal
     *
     * @param iterable<FieldEnumProviderInterface> $enumProviders
     */
    public function __construct(private readonly iterable $enumProviders = [])
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter(null, false);
    }

    public function getLastPostSchemaRef(): ?string
    {
        return $this->lastPostSchemaRef;
    }

    /**
     * @return array<string>
     */
    public function getLastPostRequiredFields(): array
    {
        return $this->lastPostRequiredFields;
    }

    /**
     * @return array<string, Schema>
     */
    public function getSchemaByDefinition(
        EntityDefinition $definition,
        string $path,
        bool $forSalesChannel,
        bool $onlyFlat = false,
        string $apiType = DefinitionService::TYPE_JSON_API
    ): array {
        $this->lastPostSchemaRef = null;
        $this->lastPostRequiredFields = [];

        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());
        $uuid = Uuid::fromStringToHex($schemaName);
        $exampleDetailPath = $path . '/' . $uuid;

        $fieldData = $this->collectFieldData($definition, $forSalesChannel, $exampleDetailPath);

        // In some entities all fields are hidden, but not the id. This creates unwanted schemas.
        // readOnlyAttributes always contains at least the id property, so check if there's anything beyond it.
        if (empty($fieldData['updateAttributes']) && empty($fieldData['immutableAttributes']) && \count($fieldData['readOnlyAttributes']) <= 1 && empty($fieldData['relationships'])) {
            return [];
        }

        $schema = [];

        // For Store API (sales channel), only generate a flat Read schema without Update/Create
        // Store API is read-only for entity resources, so Update/Create schemas are not needed
        if ($forSalesChannel) {
            return $this->buildStoreApiSchema(
                $schemaName,
                $fieldData['allAttributes'],
                $fieldData['allRequiredAttributes'],
                $fieldData['relationships'],
                $definition->since(),
                $onlyFlat,
                $apiType
            );
        }

        // For TYPE_JSON_API (Admin API), generate Update/Create/Read schemas with allOf composition
        // For TYPE_JSON, generate a flat schema (backward compatible)
        if ($apiType === DefinitionService::TYPE_JSON_API) {
            $hasImmutableFields = !empty($fieldData['immutableAttributes']);

            // Generate Update schema (modifiable fields only, no required - used for PATCH partial updates)
            $schema[$schemaName . 'Update'] = $this->buildUpdateSchema(
                $schemaName,
                $fieldData['updateAttributes'],
                $definition->since()
            );

            // Generate Create schema only when entity has immutable fields (3-part schema)
            // When no immutable fields, use 2-part schema (Update + Read)
            if ($hasImmutableFields) {
                $schema[$schemaName . 'Create'] = $this->buildCreateSchema(
                    $schemaName,
                    $fieldData['immutableAttributes'],
                    $fieldData['createRequiredAttributes'],
                    $definition->since()
                );
            }

            // Generate Read schema - all fields including technical read-only (id, createdAt, updatedAt) and relationships
            // References Create (when immutable fields exist) or Update (when no immutable fields)
            $writableSchemaRef = $hasImmutableFields ? $schemaName . 'Create' : $schemaName . 'Update';
            $schema[$schemaName] = $this->buildReadSchema(
                $schemaName,
                $fieldData['readOnlyAttributes'],
                $fieldData['relationships'],
                $definition->since(),
                $writableSchemaRef
            );

            // Store metadata for path builder to determine POST request body schema
            $this->lastPostSchemaRef = $hasImmutableFields
                ? '#/components/schemas/' . $schemaName . 'Create'
                : '#/components/schemas/' . $schemaName . 'Update';
            $this->lastPostRequiredFields = $hasImmutableFields
                ? []
                : $fieldData['createRequiredAttributes'];

            // Generate JsonApi schema for JSON:API format
            if (!$onlyFlat) {
                $schema[$schemaName . 'JsonApi'] = $this->buildJsonApiSchema(
                    $schemaName,
                    $fieldData['allAttributes'],
                    $fieldData['allRequiredAttributes'],
                    $fieldData['relationships'],
                    $definition->since()
                );
            }
        } else {
            // TYPE_JSON: Build flat schema (backward compatible structure)
            return $this->buildStoreApiSchema(
                $schemaName,
                $fieldData['allAttributes'],
                $fieldData['allRequiredAttributes'],
                $fieldData['relationships'],
                $definition->since(),
                $onlyFlat,
                $apiType
            );
        }

        return $schema;
    }

    /**
     * Collects and categorizes field data for different schema types
     *
     * Schema composition pattern:
     * - Update: modifiable fields only (no required fields for partial updates)
     * - Create: allOf[Update, {immutable fields}] - all writable fields with required
     * - Read (Entity): allOf[{read-only fields}, Create] - includes technical fields
     *
     * @return array{
     *     updateAttributes: Property[],
     *     updateRequiredAttributes: string[],
     *     immutableAttributes: Property[],
     *     immutableRequiredAttributes: string[],
     *     createRequiredAttributes: string[],
     *     readOnlyAttributes: Property[],
     *     allAttributes: Property[],
     *     allRequiredAttributes: string[],
     *     relationships: Property[]
     * }
     */
    private function collectFieldData(EntityDefinition $definition, bool $forSalesChannel, string $exampleDetailPath): array
    {
        $updateAttributes = [];
        $updateRequiredAttributes = [];
        $immutableAttributes = [];
        $immutableRequiredAttributes = [];
        $readOnlyAttributes = [];
        $allAttributes = [];
        $allRequiredAttributes = [];
        $relationships = [];
        $extensions = [];

        $defaults = $definition->getDefaults();

        foreach ($definition->getFields() as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel)) {
                continue;
            }

            // Skip primary key fields - they are added manually with specific formatting later
            if ($field->is(PrimaryKey::class)) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $extensions[] = $field;

                continue;
            }

            $isRequired = $field->is(Required::class)
                && !$field instanceof VersionField
                && !$field instanceof ReferenceVersionField
                && !$field instanceof CreatedAtField
                && !$field instanceof UpdatedAtField
                && !\array_key_exists($field->getPropertyName(), $defaults);

            // Handle associations
            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $relationships[] = $this->createToOneLinkage($field, $exampleDetailPath);

                continue;
            }

            if ($field instanceof AssociationField) {
                $relationships[] = $this->createToManyLinkage($field, $exampleDetailPath);

                continue;
            }

            // Resolve translated fields
            $resolvedField = $field;
            if ($field instanceof TranslatedField && $definition->getTranslationDefinition()) {
                $resolvedField = $definition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            }

            if ($resolvedField === null) {
                continue;
            }

            // Build property
            $attr = $resolvedField instanceof JsonField
                ? $this->resolveJsonField($resolvedField)
                : $this->getPropertyByField($resolvedField);

            $isReadOnly = $this->isReadOnlyField($field);
            $isImmutable = $field->is(Immutable::class);

            // Apply enum values from Choice flag and enum providers
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

            if ($enumValues !== [] && \in_array($attr->type, ['string', 'integer', 'number', 'boolean'], true)) {
                $attr->enum = $enumValues;
            }

            if ($isReadOnly) {
                $attr->readOnly = true;
            }

            if ($this->isDeprecated($field)) {
                $attr->deprecated = true;
            }

            // Categorize field into appropriate schema bucket
            if ($isReadOnly) {
                // Read-only fields go to readOnlyAttributes (will be in BaseEntity-like section of Read schema)
                $readOnlyAttributes[] = $attr;
                $allAttributes[] = clone $attr;
                if ($isRequired) {
                    $allRequiredAttributes[] = $field->getPropertyName();
                }
            } elseif ($isImmutable) {
                // Immutable fields go only to immutableAttributes (Create schema extends Update with these)
                $immutableAttr = clone $attr;
                $immutableAttr->description = ($attr->description ? $attr->description . ' ' : '') . 'Editable only on creation. Immutable afterwards.';
                $immutableAttributes[] = $immutableAttr;
                $allAttributes[] = clone $attr;
                if ($isRequired) {
                    $immutableRequiredAttributes[] = $field->getPropertyName();
                    $allRequiredAttributes[] = $field->getPropertyName();
                }
            } else {
                // Modifiable fields go to updateAttributes
                $updateAttributes[] = $attr;
                $allAttributes[] = clone $attr;
                if ($isRequired) {
                    $updateRequiredAttributes[] = $field->getPropertyName();
                    $allRequiredAttributes[] = $field->getPropertyName();
                }
            }
        }

        // Handle extensions - add to update schema (extensions are generally modifiable)
        $extensionAttributes = $this->getExtensions($extensions, $exampleDetailPath);
        if (!empty($extensionAttributes)) {
            $extensionsProperty = new Property([
                'property' => 'extensions',
                'type' => 'object',
                'properties' => $extensionAttributes,
            ]);
            $updateAttributes[] = $extensionsProperty;
            $allAttributes[] = clone $extensionsProperty;
        }

        // Handle translation definition required fields
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
                    && !$field instanceof FkField
                ) {
                    // Translated required fields are typically modifiable
                    $updateRequiredAttributes[] = $propertyName;
                    $allRequiredAttributes[] = $propertyName;
                }
            }
        }

        // Add id and timestamps to readOnlyAttributes
        $idProperty = new Property([
            'property' => 'id',
            'type' => 'string',
            'pattern' => '^[0-9a-f]{32}$',
            'readOnly' => true,
        ]);
        $readOnlyAttributes = [$idProperty, ...$readOnlyAttributes];
        $allAttributes = [$idProperty, ...$allAttributes];

        // Add relationship properties to allAttributes (for read/response)
        foreach ($relationships as $relationship) {
            $allAttributes[] = $this->getRelationShipProperty($relationship);
        }

        return [
            'updateAttributes' => $updateAttributes,
            'updateRequiredAttributes' => array_values(array_unique($updateRequiredAttributes)),
            'immutableAttributes' => $immutableAttributes,
            'immutableRequiredAttributes' => array_values(array_unique($immutableRequiredAttributes)),
            'createRequiredAttributes' => array_values(array_unique([...$updateRequiredAttributes, ...$immutableRequiredAttributes])),
            'readOnlyAttributes' => $readOnlyAttributes,
            'allAttributes' => $allAttributes,
            'allRequiredAttributes' => array_values(array_unique($allRequiredAttributes)),
            'relationships' => $relationships,
        ];
    }

    /**
     * Determines if a field is read-only (cannot be written at all)
     */
    private function isReadOnlyField(Field $field): bool
    {
        // Primary key fields are read-only (id is server-generated)
        if ($field->is(PrimaryKey::class)) {
            return true;
        }

        // Technical timestamp fields are read-only
        if (\in_array($field->getPropertyName(), ['createdAt', 'updatedAt'], true)) {
            return true;
        }

        // Write-protected fields are read-only for user scope
        if ($this->isWriteProtected($field)) {
            return true;
        }

        // Runtime fields are read-only
        if ($field->is(Runtime::class)) {
            return true;
        }

        return false;
    }

    /**
     * Builds the Read schema using allOf composition:
     * - Contains read-only properties inline (id, createdAt, updatedAt, etc.)
     * - Contains relationship properties inline
     * - References Create schema (when immutable fields exist) or Update schema (when no immutable fields)
     *
     * @param Property[] $readOnlyAttributes Read-only fields (id, createdAt, updatedAt, etc.)
     * @param Property[] $relationships Relationship properties (associations)
     * @param string $writableSchemaRef The schema name to reference for writable fields (e.g. 'ProductCreate' or 'ProductUpdate')
     */
    private function buildReadSchema(string $schemaName, array $readOnlyAttributes, array $relationships, ?string $since, string $writableSchemaRef): Schema
    {
        $description = 'All fields of ' . $schemaName . ' entity including read-only technical fields.';
        if (!empty($since)) {
            $description .= ' Added since version: ' . $since;
        }

        // Convert relationships to property objects and add to read-only attributes
        $inlineProperties = $readOnlyAttributes;
        foreach ($relationships as $relationship) {
            $inlineProperties[] = $this->getRelationShipProperty($relationship);
        }

        // Build the read-only and relationship properties object
        $readOnlySchemaConfig = ['type' => 'object'];
        if (!empty($inlineProperties)) {
            $readOnlySchemaConfig['properties'] = $inlineProperties;
        }
        $readOnlySchema = new Schema($readOnlySchemaConfig);

        return new Schema([
            'schema' => $schemaName,
            'description' => $description,
            'allOf' => [
                $readOnlySchema,
                new Schema(['ref' => '#/components/schemas/' . $writableSchemaRef]),
            ],
        ]);
    }

    /**
     * Builds a flat schema for Store API (sales channel) without Update/Create separation.
     * Store API is read-only for entity resources, so we don't need write operation schemas.
     *
     * @param Property[] $attributes All attributes
     * @param string[] $requiredAttributes Required field names
     * @param Property[] $relationships Relationship properties
     *
     * @return Schema[]
     */
    private function buildStoreApiSchema(
        string $schemaName,
        array $attributes,
        array $requiredAttributes,
        array $relationships,
        ?string $since,
        bool $onlyFlat,
        string $apiType
    ): array {
        $schema = [];

        // Add relationship properties to attributes
        foreach ($relationships as $relationship) {
            $attributes[] = $this->getRelationShipProperty($relationship);
        }

        // Build flat Read schema (original behavior before Update/Create split)
        $schemaConfig = [
            'type' => 'object',
            'schema' => $schemaName,
        ];

        // Only add properties if not empty to avoid empty array serialization as []
        if (!empty($attributes)) {
            $schemaConfig['properties'] = $attributes;
        }

        $schema[$schemaName] = new Schema($schemaConfig);

        if (!empty($since)) {
            $schema[$schemaName]->description = 'Added since version: ' . $since;
        }

        if ($requiredAttributes !== []) {
            $schema[$schemaName]->required = $requiredAttributes;
        }

        // Generate JsonApi schema for JSON:API format
        if (!$onlyFlat && $apiType === DefinitionService::TYPE_JSON_API) {
            $schema[$schemaName . 'JsonApi'] = $this->buildJsonApiSchema(
                $schemaName,
                $attributes,
                $requiredAttributes,
                $relationships,
                $since
            );
        }

        return $schema;
    }

    /**
     * Builds the Update schema (modifiable fields only, no required fields).
     * Used for PATCH operations where all fields are optional (partial updates).
     *
     * @param Property[] $attributes Modifiable fields only
     */
    private function buildUpdateSchema(string $schemaName, array $attributes, ?string $since): Schema
    {
        $description = 'Fields available for update operations on ' . $schemaName . ' entities. All fields are optional for partial updates.';
        if (!empty($since)) {
            $description .= ' Added since version: ' . $since;
        }

        $schemaConfig = [
            'type' => 'object',
            'schema' => $schemaName . 'Update',
            'description' => $description,
        ];

        // Only add properties if not empty to avoid empty array serialization as []
        if (!empty($attributes)) {
            $schemaConfig['properties'] = $attributes;
        }

        return new Schema($schemaConfig);
    }

    /**
     * Builds the Create schema using allOf composition.
     * - References Update schema (modifiable fields)
     * - Adds immutable fields that can only be set during creation (if any)
     * - Includes required fields for entity creation
     *
     * @param Property[] $immutableAttributes Immutable fields (may be empty)
     * @param string[] $requiredAttributes All required fields for creation (modifiable + immutable)
     */
    private function buildCreateSchema(string $schemaName, array $immutableAttributes, array $requiredAttributes, ?string $since): Schema
    {
        $description = 'Schema for creating ' . $schemaName . ' entities. Includes immutable fields that can only be set during creation.';
        if (!empty($since)) {
            $description .= ' Added since version: ' . $since;
        }

        $allOfSchemas = [
            new Schema(['ref' => '#/components/schemas/' . $schemaName . 'Update']),
        ];

        // Add immutable properties
        if (!empty($immutableAttributes)) {
            $immutableSchema = new Schema([
                'type' => 'object',
                'properties' => $immutableAttributes,
            ]);
            $allOfSchemas[] = $immutableSchema;
        }

        $schema = new Schema([
            'schema' => $schemaName . 'Create',
            'description' => $description,
            'allOf' => $allOfSchemas,
        ]);

        // Required fields apply to the Create schema overall
        if (\count($requiredAttributes)) {
            $schema->required = $requiredAttributes;
        }

        return $schema;
    }

    /**
     * Builds the JsonApi schema for JSON:API format responses
     *
     * @param Property[] $attributes
     * @param string[] $requiredAttributes
     * @param Property[] $relationships
     */
    private function buildJsonApiSchema(string $schemaName, array $attributes, array $requiredAttributes, array $relationships, ?string $since): Schema
    {
        $propertiesSchemaConfig = ['type' => 'object'];
        if (!empty($attributes)) {
            $propertiesSchemaConfig['properties'] = $attributes;
        }

        $schema = new Schema([
            'schema' => $schemaName . 'JsonApi',
            'allOf' => [
                new Schema(['ref' => '#/components/schemas/resource']),
                new Schema($propertiesSchemaConfig),
            ],
        ]);

        if (!empty($since)) {
            $schema->description = 'Added since version: ' . $since;
        }

        if (\count($requiredAttributes)) {
            $schema->allOf[1]->required = $requiredAttributes;
        }

        if (\count($relationships)) {
            // Initialize properties array if not set
            if ($schema->allOf[1]->properties === \OpenApi\Generator::UNDEFINED) {
                $schema->allOf[1]->properties = [];
            }
            $schema->allOf[1]->properties[] = new Property([
                'property' => 'relationships',
                'type' => 'object',
                'properties' => $relationships,
            ]);
        }

        return $schema;
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

        if (!$flag->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class)) {
            return false;
        }

        return true;
    }

    private function createToOneLinkage(ManyToOneAssociationField|OneToOneAssociationField $field, string $basePath): Property
    {
        $property = [
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
                            'example' => Uuid::fromStringToHex($field->getPropertyName()),
                        ],
                    ],
                ],
            ],
        ];

        if ($field->getDescription() !== '') {
            $property['description'] = $field->getDescription();
        }

        return new Property($property);
    }

    private function createToManyLinkage(ManyToManyAssociationField|OneToManyAssociationField|AssociationField $field, string $basePath): Property
    {
        $associationEntityName = $field->getReferenceDefinition()->getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $associationEntityName = $field->getToManyReferenceDefinition()->getEntityName();
        }

        $property = [
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
                                'example' => Uuid::fromStringToHex($field->getPropertyName()),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($field->getDescription() !== '') {
            $property['description'] = $field->getDescription();
        }

        return new Property($property);
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
        if ($jsonField instanceof ListField || $jsonField instanceof BreadcrumbField) {
            $definition = new Property([
                'type' => 'array',
                'property' => $jsonField->getPropertyName(),
                'items' => $this->getPropertyAssociationsByField($jsonField instanceof ListField ? $jsonField->getFieldType() : null),
            ]);
        } elseif ($jsonField instanceof PriceField) {
            $definition = new Property([
                'type' => 'array',
                'property' => $jsonField->getPropertyName(),
                'items' => new Schema(['ref' => '#/components/schemas/Price']),
            ]);
        } elseif ($jsonField instanceof MeasurementUnitsField) {
            $definition = new Property([
                'type' => 'object',
                'property' => $jsonField->getPropertyName(),
                'ref' => '#/components/schemas/MeasurementUnits',
            ]);
        } else {
            $definition = new Property([
                'type' => 'object',
                'property' => $jsonField->getPropertyName(),
            ]);
        }

        $required = [];

        if ($jsonField->getPropertyMapping() !== []) {
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

        if ($required !== []) {
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
        $fieldClass = $field::class;

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
            $property->description = $description;
        }

        return $property;
    }

    private function getPropertyAssociationsByField(?string $fieldClass): object
    {
        $property = new \stdClass();
        if ($fieldClass === null) {
            $property->type = 'object';
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
        $writeProtection = $field->getFlag(WriteProtected::class);

        return $writeProtection && !$writeProtection->isAllowed(Context::USER_SCOPE);
    }

    private function isDeprecated(Field $field): bool
    {
        return $field->getFlag(Deprecated::class) !== null;
    }

    private function getRelationShipEntity(Property $relationship): string
    {
        $relationshipData = $relationship->properties['data'];
        \assert(\is_array($relationshipData));
        $type = $relationshipData['type'];
        $entity = '';

        if ($type === 'object') {
            $entity = $relationshipData['properties']['type']['example'];
        } elseif ($type === 'array') {
            $entity = $relationshipData['items']['properties']['type']['example'];
        }

        return $entity;
    }

    private function getRelationShipProperty(Property $relationship): Property
    {
        $entity = $this->getRelationShipEntity($relationship);
        $entityName = $this->snakeCaseToCamelCase($entity);

        $relationshipData = $relationship->properties['data'];
        \assert(\is_array($relationshipData));

        $property = [
            'property' => $relationship->property,
            'description' => $relationship->description,
            // Create a context with OpenAPI 3.1.0 to ensure descriptions work with $ref
            '_context' => new OpenApiContext(['version' => OpenApi::VERSION_3_1_0]),
        ];

        if ($relationshipData['type'] === 'array') {
            $property['type'] = 'array';
            $property['items'] = new Schema(['ref' => '#/components/schemas/' . $entityName]);

            return new Property($property);
        }

        $property['ref'] = '#/components/schemas/' . $entityName;

        return new Property($property);
    }
}
