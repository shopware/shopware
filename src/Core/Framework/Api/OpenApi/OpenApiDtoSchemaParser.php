<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class OpenApiDtoSchemaParser
{
    public const PHP_TYPE_ARRAY = 'array';
    public const PHP_TYPE_BOOLEAN = 'bool';
    public const PHP_TYPE_FLOAT = 'float';
    public const PHP_TYPE_INTEGER = 'int';
    public const PHP_TYPE_MIXED = 'mixed';
    public const PHP_TYPE_STRING = 'string';

    /**
     * @var list<self::PHP_TYPE_*>
     */
    public const PHP_TYPES = [
        self::PHP_TYPE_ARRAY,
        self::PHP_TYPE_BOOLEAN,
        self::PHP_TYPE_FLOAT,
        self::PHP_TYPE_INTEGER,
        self::PHP_TYPE_MIXED,
        self::PHP_TYPE_STRING,
    ];
    /**
     * @var list<string>
     */
    private const HTTP_METHODS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'options',
        'head',
        'trace',
    ];

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<OpenApiDtoDefinition>
     */
    public function parse(array $schema, bool $includeComponentSchemas = true): array
    {
        $registry = $this->schemaRegistry($schema);

        $componentDefinitions = $includeComponentSchemas
            ? $this->parseComponentSchemas($schema, $registry)
            : $this->parseReferencedComponentSchemas($schema, $registry);

        return $this->deduplicate([
            ...$componentDefinitions,
            ...$this->parseRequestBodies($schema, $registry),
            ...$this->parseResponseBodies($schema, $registry),
        ]);
    }

    /**
     * Generates the DTOs for the given component schema names only. Used to emit shared
     * components (which declare their own target namespace) once, in their own namespace.
     *
     * @param array<string, mixed> $schema
     * @param list<string> $schemaNames
     *
     * @return list<OpenApiDtoDefinition>
     */
    public function parseComponents(array $schema, array $schemaNames): array
    {
        $registry = $this->schemaRegistry($schema);

        $definitions = [];
        foreach ($schemaNames as $name) {
            $schemaData = $registry[$name] ?? null;
            if ($schemaData === null) {
                continue;
            }

            $definitions = [
                ...$definitions,
                ...$this->extractDtoFromSchema($this->toPascalCase($name), $schemaData, $registry),
            ];
        }

        return $this->deduplicate($definitions);
    }

    /**
     * Generates DTOs only for the component schemas that are actually referenced (transitively)
     * by the operations in the given spec, instead of the whole component catalogue.
     *
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseReferencedComponentSchemas(array $schema, array $registry): array
    {
        $definitions = [];
        foreach ($this->collectReferencedSchemaNames($schema, $registry) as $name) {
            $schemaData = $registry[$name] ?? null;
            if ($schemaData === null) {
                continue;
            }

            $definitions = [
                ...$definitions,
                ...$this->extractDtoFromSchema($this->toPascalCase($name), $schemaData, $registry),
            ];
        }

        return $definitions;
    }

    /**
     * Collects the transitive closure of component schema names referenced from the operations
     * in the spec (request bodies, responses and parameters), following nested `$ref`s.
     *
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<string>
     */
    private function collectReferencedSchemaNames(array $schema, array $registry): array
    {
        $queue = $this->extractRefNames($this->arrayAtPath($schema, ['paths']) ?? []);

        $seen = [];
        while ($queue !== []) {
            $name = array_pop($queue);
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            $schemaData = $registry[$name] ?? null;
            if (\is_array($schemaData)) {
                foreach ($this->extractRefNames($schemaData) as $nestedName) {
                    if (!isset($seen[$nestedName])) {
                        $queue[] = $nestedName;
                    }
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * @return list<string>
     */
    private function extractRefNames(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        $names = [];
        foreach ($data as $key => $value) {
            if ($key === '$ref' && \is_string($value) && str_contains($value, '/components/schemas/')) {
                $names[] = $this->resolveRefName($value);

                continue;
            }

            $names = [...$names, ...$this->extractRefNames($value)];
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function schemaRegistry(array $schema): array
    {
        $schemas = $this->arrayAtPath($schema, ['components', 'schemas']);
        if ($schemas === null) {
            return [];
        }

        $registry = [];
        foreach ($schemas as $name => $value) {
            if (!\is_string($name) || !\is_array($value)) {
                continue;
            }

            $registry[$name] = $value;
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseComponentSchemas(array $schema, array $registry): array
    {
        $schemas = $this->arrayAtPath($schema, ['components', 'schemas']);
        if ($schemas === null) {
            return [];
        }

        $definitions = [];
        foreach ($schemas as $schemaName => $schemaData) {
            if (!\is_string($schemaName) || !\is_array($schemaData)) {
                continue;
            }

            $definitions = [
                ...$definitions,
                ...$this->extractDtoFromSchema($this->toPascalCase($schemaName), $schemaData, $registry),
            ];
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseRequestBodies(array $schema, array $registry): array
    {
        $paths = $this->arrayAtPath($schema, ['paths']);
        if ($paths === null) {
            return [];
        }

        $definitions = [];
        foreach ($paths as $pathData) {
            if (!\is_array($pathData)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $this->schemaAtKey($pathData, $method);
                if ($operation === null) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;
                if (!\is_string($operationId) || $operationId === '') {
                    continue;
                }

                $dtoName = $this->toPascalCase($operationId) . 'Request';
                $parameters = $this->extractParameterProperties($operation, $registry);
                $requestSchema = $this->arrayAtPath($operation, ['requestBody', 'content', 'application/json', 'schema']);

                if ($requestSchema === null) {
                    if ($parameters !== []) {
                        $definitions[] = new OpenApiDtoDefinition(
                            $dtoName,
                            $parameters,
                            $this->stringOrNull($operation['description'] ?? null),
                            $this->packageFromSchema($operation),
                        );
                    }

                    continue;
                }

                $referencedBodySchema = $this->singleAllOfReference($requestSchema);
                if ($referencedBodySchema !== null) {
                    $referenceName = $this->resolveRefName($referencedBodySchema['$ref']);
                    $requestBody = $this->schemaAtKey($operation, 'requestBody');
                    $bodyProperty = $this->createProperty(
                        $this->toPropertyName($referenceName),
                        $referencedBodySchema,
                        ($requestBody['required'] ?? null) === true,
                        $this->toPascalCase($referenceName),
                        null,
                        registry: $registry,
                    );

                    $definitions[] = new OpenApiDtoDefinition(
                        $dtoName,
                        [$bodyProperty, ...$parameters],
                        $this->stringOrNull($operation['description'] ?? null),
                        $this->packageFromSchema($operation),
                    );

                    continue;
                }

                $resolvedRequestSchema = $this->dereferenceSchema($requestSchema, $registry);
                $variants = $this->variants($resolvedRequestSchema);

                if ($variants !== null && \count($variants) > 1) {
                    $definitions = [
                        ...$definitions,
                        ...$this->extractVariantRequestDtos($dtoName, $resolvedRequestSchema, $variants, $parameters, $registry, $operation),
                    ];

                    continue;
                }

                $extracted = $this->extractFromSchema($resolvedRequestSchema, $dtoName, $registry);
                $properties = [...$extracted['properties'], ...$parameters];

                if ($properties === []) {
                    continue;
                }

                $definitions[] = new OpenApiDtoDefinition(
                    $dtoName,
                    $properties,
                    $this->stringOrNull($resolvedRequestSchema['description'] ?? null) ?? $this->stringOrNull($operation['description'] ?? null),
                    $this->packageFromSchema($resolvedRequestSchema) ?? $this->packageFromSchema($operation),
                );
                $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseResponseBodies(array $schema, array $registry): array
    {
        $paths = $this->arrayAtPath($schema, ['paths']);
        if ($paths === null) {
            return [];
        }

        $responseRegistry = $this->responseRegistry($schema);

        $definitions = [];
        foreach ($paths as $pathData) {
            if (!\is_array($pathData)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $this->schemaAtKey($pathData, $method);
                if ($operation === null) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;
                if (!\is_string($operationId) || $operationId === '') {
                    continue;
                }

                $response = $this->successResponse($operation, $responseRegistry);
                if ($response === null) {
                    continue;
                }

                $responseSchema = $this->arrayAtPath($response, ['content', 'application/json', 'schema']);
                if ($responseSchema === null) {
                    continue;
                }

                $resolvedResponseSchema = $this->dereferenceSchema($responseSchema, $registry);
                $dtoName = $this->toPascalCase($operationId) . 'Response';
                $extracted = $this->extractFromSchema($resolvedResponseSchema, $dtoName, $registry);

                if ($extracted['properties'] === []) {
                    continue;
                }

                $definitions[] = new OpenApiDtoDefinition(
                    $dtoName,
                    $extracted['properties'],
                    $this->stringOrNull($response['description'] ?? null) ?? $this->stringOrNull($operation['description'] ?? null),
                    $this->packageFromSchema($resolvedResponseSchema) ?? $this->packageFromSchema($operation),
                );
                $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function responseRegistry(array $schema): array
    {
        $responses = $this->arrayAtPath($schema, ['components', 'responses']);
        if ($responses === null) {
            return [];
        }

        $registry = [];
        foreach ($responses as $name => $response) {
            if (!\is_string($name) || !\is_array($response)) {
                continue;
            }

            $registry[$name] = $response;
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $operation
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoProperty>
     */
    private function extractParameterProperties(array $operation, array $registry): array
    {
        $parameters = $operation['parameters'] ?? null;
        if (!\is_array($parameters) || !\array_is_list($parameters)) {
            return [];
        }

        $properties = [];
        foreach ($parameters as $parameter) {
            if (!\is_array($parameter)) {
                continue;
            }

            if (($parameter['in'] ?? null) === 'header') {
                continue;
            }

            $schema = $this->schemaAtKey($parameter, 'schema');
            $name = $parameter['name'] ?? null;
            if ($schema === null || !\is_string($name) || $name === '') {
                continue;
            }

            $constraintSchema = $this->dereferenceSchema($schema, $registry);
            $type = $this->mapOpenApiTypeToPhp($schema, $registry);
            $properties[] = new OpenApiDtoProperty(
                name: $this->toPropertyName($name),
                phpType: $type['phpType'],
                required: ($parameter['required'] ?? null) === true,
                nullable: $type['nullable'],
                arrayItemType: $type['arrayItemType'],
                description: $this->stringOrNull($parameter['description'] ?? null),
                format: $this->stringOrNull($constraintSchema['format'] ?? null),
                pattern: $this->stringOrNull($constraintSchema['pattern'] ?? null),
                enum: $this->scalarEnum($constraintSchema['enum'] ?? null),
                defaultValue: $this->defaultValue($constraintSchema),
                hasDefaultValue: $this->hasDefaultValue($constraintSchema),
                minItems: $this->intOrNull($constraintSchema['minItems'] ?? null),
                minLength: $this->intOrNull($constraintSchema['minLength'] ?? null),
                arrayItemMinLength: $this->arrayItemMinLength($constraintSchema),
                unresolvedReference: $type['unresolved'],
            );
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function extractDtoFromSchema(string $name, array $schema, array $registry): array
    {
        $extracted = $this->extractFromSchema($schema, $name, $registry);
        if ($extracted['properties'] === []) {
            return [];
        }

        return [
            new OpenApiDtoDefinition(
                $name,
                $extracted['properties'],
                $this->stringOrNull($schema['description'] ?? null),
                $this->packageFromSchema($schema),
            ),
            ...$extracted['nestedDefinitions'],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return array{properties: list<OpenApiDtoProperty>, nestedDefinitions: list<OpenApiDtoDefinition>}
     */
    private function extractFromSchema(array $schema, string $parentDtoName, array $registry): array
    {
        $resolved = $this->resolveSchemaProperties($schema, $registry);

        return $this->extractPropertiesFromSchema($resolved['properties'], $resolved['required'], $parentDtoName, $registry, $this->packageFromSchema($schema));
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $requiredFields
     * @param array<string, array<string, mixed>> $registry
     *
     * @return array{properties: list<OpenApiDtoProperty>, nestedDefinitions: list<OpenApiDtoDefinition>}
     */
    private function extractPropertiesFromSchema(array $properties, array $requiredFields, string $parentDtoName, array $registry, ?string $parentPackage = null): array
    {
        $dtoProperties = [];
        $nestedDefinitions = [];

        foreach ($properties as $propertyName => $propertySchema) {
            if (!\is_string($propertyName) || !\is_array($propertySchema)) {
                continue;
            }

            $required = \in_array($propertyName, $requiredFields, true);
            if ($this->isInlineObject($propertySchema)) {
                $nestedName = $this->buildNestedDtoName($parentDtoName, $propertyName);
                $nestedExtracted = $this->extractFromSchema($propertySchema, $nestedName, $registry);
                $nestedDefinitions[] = new OpenApiDtoDefinition(
                    $nestedName,
                    $nestedExtracted['properties'],
                    $this->stringOrNull($propertySchema['description'] ?? null),
                    $this->packageFromSchema($propertySchema) ?? $parentPackage,
                );
                $nestedDefinitions = [...$nestedDefinitions, ...$nestedExtracted['nestedDefinitions']];

                $dtoProperties[] = $this->createProperty($propertyName, $propertySchema, $required, $nestedName, null, registry: $registry);

                continue;
            }

            $items = $this->schemaAtKey($propertySchema, 'items');
            if ($this->schemaType($propertySchema) === 'array' && $items !== null && $this->isInlineObject($items)) {
                $nestedName = $this->buildNestedDtoName($parentDtoName, $propertyName);
                $nestedExtracted = $this->extractFromSchema($items, $nestedName, $registry);
                $nestedDefinitions[] = new OpenApiDtoDefinition(
                    $nestedName,
                    $nestedExtracted['properties'],
                    $this->stringOrNull($items['description'] ?? null),
                    $this->packageFromSchema($items) ?? $parentPackage,
                );
                $nestedDefinitions = [...$nestedDefinitions, ...$nestedExtracted['nestedDefinitions']];

                $dtoProperties[] = $this->createProperty($propertyName, $propertySchema, $required, self::PHP_TYPE_ARRAY, $nestedName, registry: $registry);

                continue;
            }

            $type = $this->mapOpenApiTypeToPhp($propertySchema, $registry);
            $dtoProperties[] = $this->createProperty(
                $propertyName,
                $propertySchema,
                $required,
                $type['phpType'],
                $type['arrayItemType'],
                $type['nullable'],
                $type['unresolved'],
                $registry,
            );
        }

        return ['properties' => $dtoProperties, 'nestedDefinitions' => $nestedDefinitions];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     */
    private function createProperty(
        string $propertyName,
        array $schema,
        bool $required,
        string $phpType,
        ?string $arrayItemType,
        ?bool $nullable = null,
        bool $unresolvedReference = false,
        array $registry = [],
    ): OpenApiDtoProperty {
        $constraintSchema = $this->dereferenceSchema($schema, $registry);

        return new OpenApiDtoProperty(
            name: $this->toPropertyName($propertyName),
            phpType: $phpType,
            required: $required,
            nullable: $nullable ?? $this->hasTypeNull($schema),
            arrayItemType: $arrayItemType,
            description: $this->stringOrNull($schema['description'] ?? null) ?? $this->stringOrNull($constraintSchema['description'] ?? null),
            format: $this->stringOrNull($constraintSchema['format'] ?? null),
            pattern: $this->stringOrNull($constraintSchema['pattern'] ?? null),
            enum: $this->scalarEnum($constraintSchema['enum'] ?? null),
            defaultValue: $this->defaultValue($constraintSchema),
            hasDefaultValue: $this->hasDefaultValue($constraintSchema),
            minItems: $this->intOrNull($constraintSchema['minItems'] ?? null),
            minLength: $this->intOrNull($constraintSchema['minLength'] ?? null),
            arrayItemMinLength: $this->arrayItemMinLength($constraintSchema),
            unresolvedReference: $unresolvedReference,
        );
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return array{properties: array<string, mixed>, required: list<string>}
     */
    private function resolveSchemaProperties(array $schema, array $registry): array
    {
        $resolvedSchema = $this->dereferenceSchema($schema, $registry);
        $allOf = $this->schemaListAtKey($resolvedSchema, 'allOf');
        if ($allOf !== null) {
            $properties = [];
            $required = [];

            foreach ($allOf as $partialSchema) {
                $partial = $this->resolveSchemaProperties($partialSchema, $registry);
                $properties = [...$properties, ...$partial['properties']];
                $required = [...$required, ...$partial['required']];
            }

            return ['properties' => $properties, 'required' => array_values(array_unique($required))];
        }

        return [
            'properties' => $this->arrayAtKey($resolvedSchema, 'properties') ?? [],
            'required' => $this->stringListAtKey($resolvedSchema, 'required'),
        ];
    }

    /**
     * @param array<string, mixed> $requestSchema
     * @param list<array<string, mixed>> $variants
     * @param list<OpenApiDtoProperty> $parameters
     * @param array<string, array<string, mixed>> $registry
     * @param array<string, mixed> $operation
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function extractVariantRequestDtos(
        string $fallbackDtoName,
        array $requestSchema,
        array $variants,
        array $parameters,
        array $registry,
        array $operation,
    ): array {
        $definitions = [];
        $sharedProperties = $this->arrayAtKey($requestSchema, 'properties') ?? [];
        $sharedRequired = $this->stringListAtKey($requestSchema, 'required');

        foreach ($variants as $variant) {
            $resolvedVariant = $this->dereferenceSchema($variant, $registry);
            $title = $this->stringOrNull($variant['title'] ?? null);
            $variantDtoName = $title !== null && $title !== '' ? $this->toPascalCase($title) : $fallbackDtoName;

            $variantProperties = [
                ...$sharedProperties,
                ...($this->arrayAtKey($resolvedVariant, 'properties') ?? []),
            ];
            $variantRequired = [
                ...$sharedRequired,
                ...$this->stringListAtKey($resolvedVariant, 'required'),
            ];
            $extracted = $this->extractPropertiesFromSchema($variantProperties, array_values(array_unique($variantRequired)), $variantDtoName, $registry, $this->packageFromSchema($resolvedVariant) ?? $this->packageFromSchema($requestSchema));
            $properties = [...$extracted['properties'], ...$parameters];

            if ($properties === []) {
                continue;
            }

            $definitions[] = new OpenApiDtoDefinition(
                $variantDtoName,
                $properties,
                $this->stringOrNull($resolvedVariant['description'] ?? null) ?? $this->stringOrNull($operation['description'] ?? null),
                $this->packageFromSchema($resolvedVariant) ?? $this->packageFromSchema($operation),
            );
            $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return array{phpType: string, arrayItemType: ?string, nullable: bool, unresolved: bool}
     */
    private function mapOpenApiTypeToPhp(array $schema, array $registry): array
    {
        $ref = $this->stringOrNull($schema['$ref'] ?? null);
        if ($ref !== null) {
            $refName = $this->resolveRefName($ref);
            // Referenced schema is not part of the static schema set (e.g. it is generated at
            // runtime from a generic entity definition). Fall back to a plain array map.
            $resolvable = isset($registry[$refName]);
            $referencedSchema = $registry[$refName] ?? null;
            if ($referencedSchema !== null && !$this->hasDtoShape($referencedSchema)) {
                return $this->mapOpenApiTypeToPhp($referencedSchema, $registry);
            }

            return [
                'phpType' => $resolvable ? $this->toPascalCase($refName) : self::PHP_TYPE_ARRAY,
                'arrayItemType' => null,
                'nullable' => false,
                'unresolved' => !$resolvable,
            ];
        }

        $variants = $this->variants($schema);
        if ($variants !== null) {
            $nonNullVariants = array_values(array_filter(
                $variants,
                fn (array $variant): bool => !$this->isNullSchema($variant),
            ));

            $hasNull = \count($variants) !== \count($nonNullVariants);
            if (\count($nonNullVariants) === 1) {
                $mapped = $this->mapOpenApiTypeToPhp($nonNullVariants[0], $registry);

                return [
                    ...$mapped,
                    'nullable' => $hasNull || $mapped['nullable'],
                ];
            }

            return ['phpType' => self::PHP_TYPE_MIXED, 'arrayItemType' => null, 'nullable' => $hasNull, 'unresolved' => false];
        }

        $allOf = $this->schemaListAtKey($schema, 'allOf');
        if ($allOf !== null) {
            if (\count($allOf) === 1) {
                return $this->mapOpenApiTypeToPhp($allOf[0], $registry);
            }

            foreach ($allOf as $partialSchema) {
                if (isset($partialSchema['$ref']) && \is_string($partialSchema['$ref'])) {
                    return $this->mapOpenApiTypeToPhp($partialSchema, $registry);
                }
            }

            return ['phpType' => self::PHP_TYPE_MIXED, 'arrayItemType' => null, 'nullable' => false, 'unresolved' => false];
        }

        $nullable = $this->hasTypeNull($schema);
        $type = $this->schemaType($schema);

        if ($type === 'array') {
            $items = $this->schemaAtKey($schema, 'items');
            if ($items === null) {
                return ['phpType' => self::PHP_TYPE_ARRAY, 'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false];
            }

            $itemType = $this->mapOpenApiTypeToPhp($items, $registry);

            return [
                'phpType' => self::PHP_TYPE_ARRAY,
                'arrayItemType' => $itemType['phpType'] !== self::PHP_TYPE_MIXED && $itemType['phpType'] !== self::PHP_TYPE_ARRAY ? $itemType['phpType'] : null,
                'nullable' => $nullable,
                'unresolved' => false,
            ];
        }

        if ($type === 'object') {
            return ['phpType' => self::PHP_TYPE_ARRAY, 'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false];
        }

        return match ($type) {
            'string' => ['phpType' => self::PHP_TYPE_STRING,  'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false],
            'integer' => ['phpType' => self::PHP_TYPE_INTEGER,  'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false],
            'number' => ['phpType' => self::PHP_TYPE_FLOAT,  'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false],
            'boolean' => ['phpType' => self::PHP_TYPE_BOOLEAN,  'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false],
            default => ['phpType' => self::PHP_TYPE_MIXED,  'arrayItemType' => null, 'nullable' => $nullable, 'unresolved' => false],
        };
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $registry
     *
     * @return array<string, mixed>
     */
    private function dereferenceSchema(array $schema, array $registry): array
    {
        $ref = $this->stringOrNull($schema['$ref'] ?? null);
        if ($ref === null) {
            return $schema;
        }

        return $registry[$this->resolveRefName($ref)] ?? $schema;
    }

    /**
     * @param array<string, mixed> $operation
     * @param array<string, array<string, mixed>> $responseRegistry
     *
     * @return array<string, mixed>|null
     */
    private function successResponse(array $operation, array $responseRegistry): ?array
    {
        $responses = $this->arrayAtKey($operation, 'responses');
        if ($responses === null) {
            return null;
        }

        foreach (['200', '201'] as $statusCode) {
            $response = $this->schemaAtKey($responses, $statusCode);
            if ($response === null) {
                continue;
            }

            $ref = $this->stringOrNull($response['$ref'] ?? null);
            if ($ref !== null) {
                return $responseRegistry[$this->resolveRefName($ref)] ?? $response;
            }

            return $response;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<array<string, mixed>>|null
     */
    private function variants(array $schema): ?array
    {
        return $this->schemaListAtKey($schema, 'oneOf') ?? $this->schemaListAtKey($schema, 'anyOf');
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array{'$ref': string}|null
     */
    private function singleAllOfReference(array $schema): ?array
    {
        $allOf = $this->schemaListAtKey($schema, 'allOf');
        if ($allOf === null || \count($allOf) !== 1) {
            return null;
        }

        $ref = $this->stringOrNull($allOf[0]['$ref'] ?? null);
        if ($ref === null) {
            return null;
        }

        return ['$ref' => $ref];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function packageFromSchema(array $schema): ?string
    {
        return $this->stringOrNull($schema[OpenApiDtoGenerator::PACKAGE_EXTENSION] ?? null);
    }

    /**
     * @param list<OpenApiDtoDefinition> $definitions
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function deduplicate(array $definitions): array
    {
        $deduplicated = [];
        foreach ($definitions as $definition) {
            if (isset($deduplicated[$definition->name])) {
                continue;
            }

            $deduplicated[$definition->name] = $definition;
        }

        return array_values($deduplicated);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function isInlineObject(array $schema): bool
    {
        return $this->schemaType($schema) === 'object'
            && $this->arrayAtKey($schema, 'properties') !== null
            && !isset($schema['$ref']);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function hasDtoShape(array $schema): bool
    {
        return $this->arrayAtKey($schema, 'properties') !== null
            || $this->schemaListAtKey($schema, 'allOf') !== null;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function isNullSchema(array $schema): bool
    {
        $type = $schema['type'] ?? null;
        if ($type === 'null') {
            return true;
        }

        if (!\is_array($type)) {
            return false;
        }

        return $type !== [] && array_values(array_filter($type, static fn (mixed $value): bool => $value !== 'null')) === [];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function hasTypeNull(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        return \is_array($type) && \in_array('null', $type, true);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function schemaType(array $schema): ?string
    {
        $type = $schema['type'] ?? null;
        if (\is_string($type)) {
            return $type;
        }

        if (!\is_array($type)) {
            return null;
        }

        $nonNullTypes = array_values(array_filter($type, static fn (mixed $value): bool => $value !== 'null'));

        return \count($nonNullTypes) === 1 && \is_string($nonNullTypes[0]) ? $nonNullTypes[0] : null;
    }

    private function resolveRefName(string $ref): string
    {
        $parts = explode('/', $ref);

        return urldecode((string) end($parts));
    }

    private function buildNestedDtoName(string $parentDtoName, string $propertyName): string
    {
        return $parentDtoName . $this->toPascalCase($propertyName);
    }

    private function toPascalCase(string $value): string
    {
        $segments = preg_split('/[^a-zA-Z0-9]+/', $value);
        \assert(\is_array($segments));

        $name = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $name .= ucfirst($segment);
        }

        if ($name === '') {
            return 'Generated';
        }

        return preg_match('/^[0-9]/', $name) === 1 ? '_' . $name : $name;
    }

    private function toPropertyName(string $value): string
    {
        $name = lcfirst($this->toPascalCase($value));

        return preg_match('/^[0-9]/', $name) === 1 ? '_' . $name : $name;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function hasDefaultValue(array $schema): bool
    {
        if (\array_key_exists('default', $schema)) {
            return $this->defaultValue($schema) !== null;
        }

        $enum = $schema['enum'] ?? null;

        return \is_array($enum) && \count($enum) === 1 && $this->defaultScalarValue($enum[0]) !== null;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function defaultValue(array $schema): string|int|float|bool|null
    {
        if (\array_key_exists('default', $schema)) {
            return $this->defaultScalarValue($schema['default']);
        }

        $enum = $schema['enum'] ?? null;
        if (!\is_array($enum) || \count($enum) !== 1) {
            return null;
        }

        return $this->defaultScalarValue($enum[0]);
    }

    private function defaultScalarValue(mixed $value): string|int|float|bool|null
    {
        return \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value) ? $value : null;
    }

    /**
     * @return list<string|int|float|bool>|null
     */
    private function scalarEnum(mixed $value): ?array
    {
        if (!\is_array($value) || $value === []) {
            return null;
        }

        $enum = [];
        foreach ($value as $case) {
            if (!\is_string($case) && !\is_int($case) && !\is_float($case) && !\is_bool($case)) {
                return null;
            }

            $enum[] = $case;
        }

        return $enum;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function arrayItemMinLength(array $schema): ?int
    {
        $items = $this->schemaAtKey($schema, 'items');

        return $items !== null ? $this->intOrNull($items['minLength'] ?? null) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>|null
     */
    private function schemaAtKey(array $source, string $key): ?array
    {
        $value = $source[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>|null
     */
    private function arrayAtKey(array $source, string $key): ?array
    {
        $value = $source[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $path
     *
     * @return array<string, mixed>|null
     */
    private function arrayAtPath(array $source, array $path): ?array
    {
        $current = $source;
        foreach ($path as $key) {
            $next = $current[$key] ?? null;
            if (!\is_array($next)) {
                return null;
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return list<array<string, mixed>>|null
     */
    private function schemaListAtKey(array $source, string $key): ?array
    {
        $value = $source[$key] ?? null;
        if (!\is_array($value) || !\array_is_list($value)) {
            return null;
        }

        $schemas = [];
        foreach ($value as $item) {
            if (!\is_array($item)) {
                return null;
            }

            $schemas[] = $item;
        }

        return $schemas;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return list<string>
     */
    private function stringListAtKey(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!\is_array($value) || !\array_is_list($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
