<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use OpenApi\Annotations as OA;
use OpenApi\OpenApiException;
use OpenApi\Undefined;
use Shopware\Core\Framework\FrameworkException;
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

    private OA\OpenApi $document;

    public function __construct(private readonly OpenApiOperationReader $operationReader)
    {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<OpenApiDtoDefinition>
     */
    public function parse(array $schema, bool $includeComponentSchemas = true, ?string $namespace = null): array
    {
        $schema = $this->deserialize($schema);
        $schema = $this->resolveOperationInputs($schema);
        $registry = $this->schemaRegistry($schema);
        $registry = $this->inheritComponentPackages(
            $schema,
            $registry,
            $includeComponentSchemas ? array_keys($registry) : [],
        );
        $componentTypes = $this->componentRootTypes($schema);

        $componentDefinitions = $includeComponentSchemas
            ? $this->parseComponentSchemas($registry, $componentTypes)
            : $this->parseReferencedComponentSchemas($schema, $registry, $componentTypes);

        $operationDefinitions = [
            ...$this->parseRequestBodies($schema, $registry),
            ...$this->parseResponseBodies($schema, $registry),
        ];
        $this->assertNoComponentOperationNameCollisions($componentDefinitions, $operationDefinitions);

        $definitions = $this->deduplicate([
            ...$componentDefinitions,
            ...$operationDefinitions,
        ]);

        return $namespace === null ? $definitions : $this->qualifyDefinitions($definitions, $namespace);
    }

    /**
     * Generates the DTOs for the given component schema names only. Used to emit shared
     * components (which declare their own target namespace) once, in their own namespace.
     *
     * @param array<string, mixed> $schema
     * @param list<string> $schemaNames
     * @param string $namespace Target namespace, resolved once by the generator.
     *
     * @return list<OpenApiDtoDefinition>
     */
    public function parseComponents(array $schema, array $schemaNames, string $namespace): array
    {
        $schema = $this->deserialize($schema);
        $schema = $this->resolveOperationInputs($schema);
        $registry = $this->schemaRegistry($schema);
        $registry = $this->inheritComponentPackages($schema, $registry, $schemaNames, includePaths: false);
        $componentTypes = $this->componentRootTypes($schema);

        $definitions = [];
        foreach ($this->collectReferencedComponentNames($schemaNames, $registry) as $name) {
            $schemaData = $registry[$name] ?? null;
            if ($schemaData === null || $this->isInlineComponent($schemaData)) {
                continue;
            }

            $componentDefinitions = $this->withComponentName(
                $this->extractDtoFromSchema($this->toPascalCase($name), $schemaData, $registry, $componentTypes[$name] ?? OpenApiDtoType::Nested),
                $name,
            );
            $definitions = [...$definitions, ...$this->qualifyDefinitions($componentDefinitions, $namespace)];
        }

        return $this->deduplicate($definitions);
    }

    private function resolveOperationInputs(OA\OpenApi $schema): OA\OpenApi
    {
        $paths = $this->arrayAtPath($schema, ['paths']) ?? [];
        foreach ($paths as $pathData) {
            if (!$pathData instanceof OA\PathItem) {
                continue;
            }

            foreach ($this->operationReader->read($pathData) as $entry) {
                $operation = $entry['operation'];

                $requestBody = $this->schemaAtKey($operation, 'requestBody');
                if ($requestBody !== null) {
                    $requestBody = $this->resolveComponentReference($requestBody, 'requestBodies');
                    if (!$requestBody instanceof OA\RequestBody) {
                        throw FrameworkException::invalidArgumentException('Expected a request body.');
                    }
                    $operation->requestBody = $this->resolveJsonContent($requestBody);
                }

                $responses = [];
                foreach ($this->arrayAtKey($operation, 'responses') ?? [] as $statusCode => $response) {
                    if (!$response instanceof OA\Response) {
                        continue;
                    }
                    if (preg_match('/^2\d{2}$/D', (string) $statusCode) === 1) {
                        $response = $this->resolveComponentReference($response, 'responses');
                        if (!$response instanceof OA\Response) {
                            throw FrameworkException::invalidArgumentException('Expected a response.');
                        }
                        $response = $this->resolveJsonContent($response);
                    }
                    $responses[] = $response;
                }
                $operation->responses = $responses;

                $parameters = [];
                foreach ([...($this->arrayAtKey($pathData, 'parameters') ?? []), ...($this->arrayAtKey($operation, 'parameters') ?? [])] as $parameter) {
                    if (!$parameter instanceof OA\AbstractAnnotation) {
                        continue;
                    }

                    $parameter = $this->resolveComponentReference($parameter, 'parameters');
                    if (!$parameter instanceof OA\Parameter) {
                        throw FrameworkException::invalidArgumentException('Expected a parameter.');
                    }
                    $name = $this->get($parameter, 'name');
                    $location = $this->get($parameter, 'in');
                    if (!\is_string($name) || !\is_string($location)) {
                        continue;
                    }

                    // Operations override inherited parameters with the same name and location.
                    $parameters[$location . ':' . $name] = $parameter;
                }
                $operation->parameters = array_values($parameters);
            }
        }

        return $schema;
    }

    private function resolveComponentReference(OA\AbstractAnnotation $input, string $section, bool $allowUnresolved = true): OA\AbstractAnnotation
    {
        $seen = [];
        while (($reference = $this->refFromSchema($input)) !== null) {
            $prefix = '#/components/' . $section . '/';
            if (!str_starts_with($reference, $prefix) || isset($seen[$reference])) {
                throw FrameworkException::invalidArgumentException('Cannot resolve reference "' . $reference . '".');
            }
            $seen[$reference] = true;
            try {
                $resolved = $this->document->ref($reference);
            } catch (OpenApiException) {
                if (!$allowUnresolved) {
                    throw FrameworkException::invalidArgumentException('Cannot resolve reference "' . $reference . '".');
                }

                return $input;
            }
            if (!$resolved instanceof OA\AbstractAnnotation) {
                throw FrameworkException::invalidArgumentException('Cannot resolve reference "' . $reference . '".');
            }
            if ($section !== 'mediaTypes') {
                $siblings = $this->children($input);
                unset($siblings['ref']);
                $class = $resolved::class;
                $resolved = new $class([...$this->children($resolved), ...$siblings, '_context' => $resolved->_context]);
            }
            $input = $resolved;
        }

        return $input;
    }

    /**
     * @template T of OA\AbstractAnnotation
     *
     * @param T $container
     *
     * @return T
     */
    private function resolveJsonContent(OA\AbstractAnnotation $container): OA\AbstractAnnotation
    {
        $mediaType = $this->nodeAtPath($container, ['content', 'application/json']);
        if ($mediaType !== null && ($container instanceof OA\RequestBody || $container instanceof OA\Response) && \is_array($container->content)) {
            $mediaType = $this->resolveComponentReference($mediaType, 'mediaTypes', allowUnresolved: false);
            if (!$mediaType instanceof OA\MediaType) {
                throw FrameworkException::invalidArgumentException('Expected a media type.');
            }
            $container->content['application/json'] = $mediaType;
        }

        return $container;
    }

    /**
     * @param list<OpenApiDtoDefinition> $definitions
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function qualifyDefinitions(array $definitions, string $namespace): array
    {
        $namespace = trim($namespace, '\\');

        return array_map(
            fn (OpenApiDtoDefinition $definition): OpenApiDtoDefinition => new OpenApiDtoDefinition(
                name: $namespace . '\\' . ltrim($definition->name, '\\'),
                properties: $definition->properties,
                description: $definition->description,
                package: $definition->package,
                enumValues: $definition->enumValues,
                enumType: $definition->enumType,
                responseStatusCode: $definition->responseStatusCode,
                type: $definition->type,
                componentName: $definition->componentName,
            ),
            $definitions,
        );
    }

    /**
     * Generates DTOs only for the component schemas that are actually referenced (transitively)
     * by the operations in the given spec, instead of the whole component catalogue.
     *
     * @param array<string, OA\Schema> $registry
     * @param array<string, OpenApiDtoType> $componentTypes
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseReferencedComponentSchemas(OA\AbstractAnnotation $schema, array $registry, array $componentTypes): array
    {
        $definitions = [];
        foreach ($this->collectReferencedSchemaNames($schema, $registry) as $name) {
            $schemaData = $registry[$name] ?? null;
            if ($schemaData === null || $this->isInlineComponent($schemaData)) {
                continue;
            }

            $definitions = [
                ...$definitions,
                ...$this->withComponentName(
                    $this->extractDtoFromSchema($this->toPascalCase($name), $schemaData, $registry, $componentTypes[$name] ?? OpenApiDtoType::Nested),
                    $name,
                ),
            ];
        }

        return $definitions;
    }

    /**
     * Collects the transitive closure of component schema names referenced from the operations
     * in the spec (request bodies, responses and parameters), following nested `$ref`s.
     *
     * @param array<string, OA\Schema> $registry
     *
     * @return list<string>
     */
    private function collectReferencedSchemaNames(OA\AbstractAnnotation $schema, array $registry): array
    {
        return $this->collectReferencedComponentNames(
            $this->extractRefNames($this->arrayAtPath($schema, ['paths']) ?? []),
            $registry,
        );
    }

    /**
     * @param list<string> $rootComponentNames
     * @param array<string, OA\Schema> $registry
     *
     * @return list<string>
     */
    private function collectReferencedComponentNames(array $rootComponentNames, array $registry): array
    {
        $queue = $rootComponentNames;
        $seen = [];
        while ($queue !== []) {
            $name = array_pop($queue);
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            $schemaData = $registry[$name] ?? null;
            if ($schemaData instanceof OA\AbstractAnnotation) {
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
        if ($data instanceof OA\AbstractAnnotation) {
            $data = $this->children($data);
        }
        if (!\is_array($data)) {
            return [];
        }

        $names = [];
        foreach ($data as $key => $value) {
            if ($key === 'ref') {
                $references = \is_array($value) ? $value : [$value];
                foreach ($references as $reference) {
                    if (\is_string($reference) && str_contains($reference, '/components/schemas/')) {
                        $names[] = $this->resolveRefName($reference);
                    }
                }

                continue;
            }

            $names = [...$names, ...$this->extractRefNames($value)];
        }

        return $names;
    }

    /**
     * @return array<string, OpenApiDtoType>
     */
    private function componentRootTypes(OA\AbstractAnnotation $schema): array
    {
        $types = [];
        foreach ($this->arrayAtPath($schema, ['paths']) ?? [] as $pathData) {
            if (!$pathData instanceof OA\PathItem) {
                continue;
            }

            foreach ($this->operationReader->read($pathData) as $entry) {
                $operation = $entry['operation'];

                $request = $this->nodeAtPath($operation, ['requestBody', 'content', 'application/json', 'schema']);
                $requestRef = $request !== null ? $this->refFromSchema($request) : null;
                if ($requestRef !== null) {
                    $this->addComponentRootType($types, $this->resolveRefName($requestRef), OpenApiDtoType::Request);
                }

                foreach ($this->get($operation, 'responses') ?? [] as $statusCode => $response) {
                    if (preg_match('/^2\d{2}$/D', (string) $statusCode) !== 1) {
                        continue;
                    }

                    if (!$response instanceof OA\AbstractAnnotation) {
                        continue;
                    }

                    $responseSchema = $this->nodeAtPath($response, ['content', 'application/json', 'schema']);
                    $responseRef = $responseSchema !== null ? $this->refFromSchema($responseSchema) : null;
                    if ($responseRef !== null) {
                        $this->addComponentRootType($types, $this->resolveRefName($responseRef), OpenApiDtoType::Response);
                    }
                }
            }
        }

        return $types;
    }

    /**
     * @param array<string, OpenApiDtoType> $types
     */
    private function addComponentRootType(array &$types, string $name, OpenApiDtoType $type): void
    {
        if (($types[$name] ?? null) === OpenApiDtoType::Nested) {
            return;
        }

        if (isset($types[$name]) && $types[$name] !== $type) {
            $types[$name] = OpenApiDtoType::Nested;

            return;
        }

        $types[$name] = $type;
    }

    /**
     * @return array<string, OA\Schema>
     */
    private function schemaRegistry(OA\AbstractAnnotation $schema): array
    {
        $schemas = $this->arrayAtPath($schema, ['components', 'schemas']);
        if ($schemas === null) {
            return [];
        }

        $registry = [];
        foreach ($schemas as $name => $value) {
            if (!\is_string($name) || !($value instanceof OA\Schema)) {
                continue;
            }

            $registry[$name] = $value;
        }

        return $registry;
    }

    /**
     * @param array<string, OA\Schema> $registry
     * @param list<string> $rootComponentNames
     *
     * @return array<string, OA\Schema>
     */
    private function inheritComponentPackages(OA\AbstractAnnotation $schema, array $registry, array $rootComponentNames, bool $includePaths = true): array
    {
        /** @var array<string, string|null> $packages */
        $packages = [];
        /** @var array<string, true> $reachedComponents */
        $reachedComponents = [];
        /** @var array<string, true> $visitedComponents */
        $visitedComponents = [];

        if ($includePaths) {
            $this->collectInheritedComponentPackages(
                $this->arrayAtPath($schema, ['paths']) ?? [],
                null,
                $registry,
                $packages,
                $reachedComponents,
                $visitedComponents,
            );
        }

        foreach ($rootComponentNames as $name) {
            if (isset($reachedComponents[$name]) || !isset($registry[$name])) {
                continue;
            }

            $this->inheritPackageForComponent(
                $name,
                null,
                $registry,
                $packages,
                $reachedComponents,
                $visitedComponents,
            );
        }

        foreach ($packages as $name => $package) {
            if ($package === null || $this->packageFromSchema($registry[$name]) !== null) {
                continue;
            }

            if (!\is_array($registry[$name]->x)) {
                $registry[$name]->x = [];
            }
            $registry[$name]->x['dto-package'] = $package;
        }

        return $registry;
    }

    /**
     * @param array<string, OA\Schema> $registry
     * @param array<string, string|null> $packages
     * @param array<string, true> $reachedComponents
     * @param array<string, true> $visitedComponents
     */
    private function collectInheritedComponentPackages(
        mixed $value,
        ?string $inheritedPackage,
        array $registry,
        array &$packages,
        array &$reachedComponents,
        array &$visitedComponents,
    ): void {
        if (!\is_array($value) && !$value instanceof OA\AbstractAnnotation) {
            return;
        }

        $package = $value instanceof OA\AbstractAnnotation ? ($this->packageFromSchema($value) ?? $inheritedPackage) : $inheritedPackage;
        $references = $this->get($value, '$ref') ?? [];
        $references = \is_array($references) ? $references : [$references];

        foreach ($references as $reference) {
            if (!\is_string($reference) || !str_contains($reference, '/components/schemas/')) {
                continue;
            }

            $name = $this->resolveRefName($reference);
            if (!isset($registry[$name])) {
                continue;
            }

            $this->inheritPackageForComponent(
                $name,
                $package,
                $registry,
                $packages,
                $reachedComponents,
                $visitedComponents,
            );
        }

        foreach ($value instanceof OA\AbstractAnnotation ? $this->children($value) : $value as $key => $nestedValue) {
            if ($key === 'ref') {
                continue;
            }

            $this->collectInheritedComponentPackages(
                $nestedValue,
                $package,
                $registry,
                $packages,
                $reachedComponents,
                $visitedComponents,
            );
        }
    }

    /**
     * @param array<string, OA\Schema> $registry
     * @param array<string, string|null> $packages
     * @param array<string, true> $reachedComponents
     * @param array<string, true> $visitedComponents
     */
    private function inheritPackageForComponent(
        string $name,
        ?string $inheritedPackage,
        array $registry,
        array &$packages,
        array &$reachedComponents,
        array &$visitedComponents,
    ): void {
        $schema = $registry[$name];
        $explicitPackage = $this->packageFromSchema($schema);
        $package = $explicitPackage ?? $inheritedPackage;
        $reachedComponents[$name] = true;

        if ($explicitPackage === null && \array_key_exists($name, $packages) && $packages[$name] !== $package) {
            throw FrameworkException::invalidArgumentException(\sprintf(
                'Component schema "%s" inherits conflicting DTO packages "%s" and "%s". Set "%s" explicitly on the component.',
                $name,
                $packages[$name] ?? 'none',
                $package ?? 'none',
                OpenApiDtoGenerator::PACKAGE_EXTENSION,
            ));
        }

        $packages[$name] = $package;
        $visitKey = $name . "\0" . ($package ?? "\0");
        if (isset($visitedComponents[$visitKey])) {
            return;
        }

        $visitedComponents[$visitKey] = true;
        $this->collectInheritedComponentPackages(
            $schema,
            $package,
            $registry,
            $packages,
            $reachedComponents,
            $visitedComponents,
        );
    }

    /**
     * @param array<string, OA\Schema> $registry
     * @param array<string, OpenApiDtoType> $componentTypes
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseComponentSchemas(array $registry, array $componentTypes): array
    {
        $definitions = [];
        foreach ($registry as $schemaName => $schemaData) {
            if (!\is_string($schemaName) || !($schemaData instanceof OA\AbstractAnnotation) || $this->isInlineComponent($schemaData)) {
                continue;
            }

            $definitions = [
                ...$definitions,
                ...$this->withComponentName(
                    $this->extractDtoFromSchema($this->toPascalCase($schemaName), $schemaData, $registry, $componentTypes[$schemaName] ?? OpenApiDtoType::Nested),
                    $schemaName,
                ),
            ];
        }

        return $definitions;
    }

    /**
     * @param list<OpenApiDtoDefinition> $definitions
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function withComponentName(array $definitions, string $componentName): array
    {
        return array_map(
            static fn (OpenApiDtoDefinition $definition): OpenApiDtoDefinition => new OpenApiDtoDefinition(
                name: $definition->name,
                properties: $definition->properties,
                description: $definition->description,
                package: $definition->package,
                enumValues: $definition->enumValues,
                enumType: $definition->enumType,
                responseStatusCode: $definition->responseStatusCode,
                type: $definition->type,
                componentName: $componentName,
            ),
            $definitions,
        );
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseRequestBodies(OA\AbstractAnnotation $schema, array $registry): array
    {
        $paths = $this->arrayAtPath($schema, ['paths']);
        if ($paths === null) {
            return [];
        }

        $definitions = [];
        foreach ($paths as $pathData) {
            if (!$pathData instanceof OA\PathItem) {
                continue;
            }

            foreach ($this->operationReader->read($pathData) as $entry) {
                $operation = $entry['operation'];

                $operationId = $this->get($operation, 'operationId');
                if (!\is_string($operationId) || $operationId === '') {
                    continue;
                }

                $dtoName = $this->toPascalCase($operationId) . 'Request';
                $parameters = $this->extractParameterProperties($operation, $registry);
                $requestSchema = $this->nodeAtPath($operation, ['requestBody', 'content', 'application/json', 'schema']);

                if ($requestSchema === null) {
                    if ($parameters !== []) {
                        $definitions[] = new OpenApiDtoDefinition(
                            $dtoName,
                            $parameters,
                            $this->stringOrNull($this->get($operation, 'description')),
                            $this->packageFromSchema($operation),
                            type: OpenApiDtoType::Request,
                        );
                    }

                    continue;
                }

                $referencedBodySchema = $this->singleAllOfReference($requestSchema);
                if ($referencedBodySchema !== null) {
                    $referenceName = $this->resolveRefName($this->get($referencedBodySchema, '$ref'));
                    $requestBody = $this->schemaAtKey($operation, 'requestBody');
                    $bodyProperty = $this->createProperty(
                        $this->toPropertyName($referenceName),
                        $referencedBodySchema,
                        $this->get($requestBody, 'required') === true,
                        $this->toPascalCase($referenceName),
                        null,
                        registry: $registry,
                    );

                    $definitions[] = new OpenApiDtoDefinition(
                        $dtoName,
                        $this->mergeProperties([$bodyProperty], $parameters),
                        $this->stringOrNull($this->get($operation, 'description')),
                        $this->packageFromSchema($operation),
                        type: OpenApiDtoType::Request,
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
                $properties = $this->mergeProperties($extracted['properties'], $parameters);

                if ($properties === []) {
                    continue;
                }

                $definitions[] = new OpenApiDtoDefinition(
                    $dtoName,
                    $properties,
                    $this->stringOrNull($this->get($resolvedRequestSchema, 'description')) ?? $this->stringOrNull($this->get($operation, 'description')),
                    $this->packageFromSchema($resolvedRequestSchema) ?? $this->packageFromSchema($operation),
                    type: OpenApiDtoType::Request,
                );
                $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function parseResponseBodies(OA\AbstractAnnotation $schema, array $registry): array
    {
        $paths = $this->arrayAtPath($schema, ['paths']);
        if ($paths === null) {
            return [];
        }

        $definitions = [];
        foreach ($paths as $pathData) {
            if (!$pathData instanceof OA\PathItem) {
                continue;
            }

            foreach ($this->operationReader->read($pathData) as $entry) {
                $operation = $entry['operation'];

                $operationId = $this->get($operation, 'operationId');
                if (!\is_string($operationId) || $operationId === '') {
                    continue;
                }

                $response = $this->successResponse($operation);
                if ($response === null) {
                    continue;
                }

                $responseSchema = $this->nodeAtPath($response['response'], ['content', 'application/json', 'schema']);
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
                    $this->stringOrNull($this->get($response['response'], 'description')) ?? $this->stringOrNull($this->get($operation, 'description')),
                    $this->packageFromSchema($resolvedResponseSchema) ?? $this->packageFromSchema($operation),
                    responseStatusCode: $response['statusCode'],
                    type: OpenApiDtoType::Response,
                );
                $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return list<OpenApiDtoProperty>
     */
    private function extractParameterProperties(OA\AbstractAnnotation $operation, array $registry): array
    {
        $parameters = $this->get($operation, 'parameters');
        if (!\is_array($parameters) || !\array_is_list($parameters)) {
            return [];
        }

        $properties = [];
        foreach ($parameters as $parameter) {
            if (!$parameter instanceof OA\AbstractAnnotation) {
                continue;
            }

            if ($this->get($parameter, 'in') === 'header') {
                continue;
            }

            $schema = $this->schemaAtKey($parameter, 'schema');
            $name = $this->get($parameter, 'name');
            if ($schema === null || !\is_string($name) || $name === '') {
                continue;
            }

            $constraintSchema = $this->dereferenceSchema($schema, $registry);
            $defaultSchema = $this->hasDefault($schema) ? $schema : $constraintSchema;
            $type = $this->mapOpenApiTypeToPhp($schema, $registry);
            $properties = $this->mergeProperties($properties, [new OpenApiDtoProperty(
                name: $this->toPropertyName($name),
                phpType: $type['phpType'],
                required: ($this->get($parameter, 'required')) === true,
                nullable: $type['nullable'],
                arrayItemType: $type['arrayItemType'],
                description: $this->stringOrNull($this->get($parameter, 'description')),
                format: $this->stringOrNull($this->get($constraintSchema, 'format')),
                pattern: $this->stringOrNull($this->get($constraintSchema, 'pattern')),
                enum: $this->isNativeEnumReference($schema, $registry) ? null : $this->scalarEnum($this->get($constraintSchema, 'enum')),
                minItems: $this->intOrNull($this->get($constraintSchema, 'minItems')),
                minLength: $this->intOrNull($this->get($constraintSchema, 'minLength')),
                maxLength: $this->intOrNull($this->get($constraintSchema, 'maxLength')),
                arrayItemMinLength: $this->arrayItemMinLength($constraintSchema),
                arrayItemMaxLength: $this->arrayItemMaxLength($constraintSchema),
                unresolvedReference: $type['unresolved'],
                nativeEnum: $this->isNativeEnumReference($schema, $registry),
                schemaName: $name,
                constValue: $this->scalarValue($this->get($constraintSchema, 'const')),
                defaultValue: $this->get($defaultSchema, 'default'),
                hasDefaultValue: $this->hasDefault($defaultSchema),
            )]);
        }

        return $properties;
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function extractDtoFromSchema(string $name, OA\AbstractAnnotation $schema, array $registry, OpenApiDtoType $type): array
    {
        $enumType = $this->nativeEnumType($schema);
        if ($enumType !== null) {
            return [new OpenApiDtoDefinition(
                $name,
                [],
                $this->stringOrNull($this->get($schema, 'description')),
                $this->packageFromSchema($schema),
                $this->scalarEnum($this->get($schema, 'enum')) ?? [],
                $enumType,
            )];
        }

        $variants = $this->variants($schema);
        if ($variants !== null && \count($variants) > 1) {
            $definitions = [];
            foreach ($variants as $variant) {
                $variantName = $this->stringOrNull($this->get($variant, 'title'));
                $ref = $this->refFromSchema($variant);
                if ($variantName === null && $ref !== null) {
                    $variantName = $this->resolveRefName($ref);
                }

                if ($variantName === null) {
                    continue;
                }

                $extracted = $this->extractFromSchema($variant, $this->toPascalCase($variantName), $registry);
                if ($extracted['properties'] === []) {
                    continue;
                }

                $definitions[] = new OpenApiDtoDefinition(
                    $this->toPascalCase($variantName),
                    $extracted['properties'],
                    $this->stringOrNull($this->get($variant, 'description')),
                    $this->packageFromSchema($variant)
                        ?? $this->packageFromSchema($this->dereferenceSchema($variant, $registry))
                        ?? $this->packageFromSchema($schema),
                    type: $type,
                );
                $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
            }

            return $this->deduplicate($definitions);
        }

        $extracted = $this->extractFromSchema($schema, $name, $registry);
        if ($extracted['properties'] === []) {
            return [];
        }

        return [
            new OpenApiDtoDefinition(
                $name,
                $extracted['properties'],
                $this->stringOrNull($this->get($schema, 'description')),
                $this->packageFromSchema($schema),
                type: $type,
            ),
            ...$extracted['nestedDefinitions'],
        ];
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return array{properties: list<OpenApiDtoProperty>, nestedDefinitions: list<OpenApiDtoDefinition>}
     */
    private function extractFromSchema(OA\AbstractAnnotation $schema, string $parentDtoName, array $registry): array
    {
        $resolved = $this->resolveSchemaProperties($schema, $registry);
        $resolvedSchema = $this->dereferenceSchema($schema, $registry);

        return $this->extractPropertiesFromSchema(
            $resolved['properties'],
            $resolved['required'],
            $parentDtoName,
            $registry,
            $this->packageFromSchema($schema) ?? $this->packageFromSchema($resolvedSchema),
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $requiredFields
     * @param array<string, OA\Schema> $registry
     *
     * @return array{properties: list<OpenApiDtoProperty>, nestedDefinitions: list<OpenApiDtoDefinition>}
     */
    private function extractPropertiesFromSchema(array $properties, array $requiredFields, string $parentDtoName, array $registry, ?string $parentPackage): array
    {
        $dtoProperties = [];
        $nestedDefinitions = [];
        $propertyNames = [];

        foreach ($properties as $propertyName => $propertySchema) {
            if (!\is_string($propertyName) || !($propertySchema instanceof OA\AbstractAnnotation)) {
                continue;
            }

            if ($propertyName === 'extensions') {
                continue;
            }

            $normalizedPropertyName = $this->toPropertyName($propertyName);
            if (isset($propertyNames[$normalizedPropertyName])) {
                throw FrameworkException::invalidArgumentException(
                    \sprintf('Schema properties "%s" and "%s" produce duplicate PHP property name "%s".', $propertyNames[$normalizedPropertyName], $propertyName, $normalizedPropertyName),
                );
            }
            $propertyNames[$normalizedPropertyName] = $propertyName;

            $required = \in_array($propertyName, $requiredFields, true);
            if ($this->isInlineObject($propertySchema)) {
                $nestedName = $this->buildNestedDtoName($parentDtoName, $propertyName);
                $nestedExtracted = $this->extractFromSchema($propertySchema, $nestedName, $registry);
                $nestedDefinitions[] = new OpenApiDtoDefinition(
                    $nestedName,
                    $nestedExtracted['properties'],
                    $this->stringOrNull($this->get($propertySchema, 'description')),
                    $this->packageFromSchema($propertySchema) ?? $parentPackage,
                    type: OpenApiDtoType::Nested,
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
                    $this->stringOrNull($this->get($items, 'description')),
                    $this->packageFromSchema($items) ?? $parentPackage,
                    type: OpenApiDtoType::Nested,
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
     * @param array<string, OA\Schema> $registry
     */
    private function createProperty(
        string $propertyName,
        OA\AbstractAnnotation $schema,
        bool $required,
        string $phpType,
        ?string $arrayItemType,
        ?bool $nullable = null,
        bool $unresolvedReference = false,
        array $registry = [],
    ): OpenApiDtoProperty {
        $constraintSchema = $this->dereferenceSchema($schema, $registry);
        $defaultSchema = $this->hasDefault($schema) ? $schema : $constraintSchema;

        return new OpenApiDtoProperty(
            name: $this->toPropertyName($propertyName),
            phpType: $phpType,
            required: $required,
            nullable: $nullable ?? $this->hasTypeNull($schema),
            arrayItemType: $arrayItemType,
            description: $this->stringOrNull($this->get($schema, 'description')),
            format: $this->stringOrNull($this->get($constraintSchema, 'format')),
            pattern: $this->stringOrNull($this->get($constraintSchema, 'pattern')),
            enum: $this->isNativeEnumReference($schema, $registry) ? null : $this->scalarEnum($this->get($constraintSchema, 'enum')),
            minItems: $this->intOrNull($this->get($constraintSchema, 'minItems')),
            minLength: $this->intOrNull($this->get($constraintSchema, 'minLength')),
            maxLength: $this->intOrNull($this->get($constraintSchema, 'maxLength')),
            arrayItemMinLength: $this->arrayItemMinLength($constraintSchema),
            arrayItemMaxLength: $this->arrayItemMaxLength($constraintSchema),
            unresolvedReference: $unresolvedReference,
            arrayMapValueType: $phpType === self::PHP_TYPE_ARRAY ? $this->arrayMapValueType($constraintSchema, $registry) : null,
            nativeEnum: $this->isNativeEnumReference($schema, $registry),
            schemaName: $propertyName,
            constValue: $this->scalarValue($this->get($constraintSchema, 'const')),
            defaultValue: $this->get($defaultSchema, 'default'),
            hasDefaultValue: $this->hasDefault($defaultSchema),
        );
    }

    /**
     * @param array<string, OA\Schema> $registry
     */
    private function isNativeEnumReference(OA\AbstractAnnotation $schema, array $registry): bool
    {
        $ref = $this->refFromSchema($schema);

        $resolved = $ref !== null ? ($registry[$this->resolveRefName($ref)] ?? null) : null;

        return $resolved !== null && $this->nativeEnumType($resolved) !== null;
    }

    /**
     * @param array<string, OA\Schema> $registry
     */
    private function arrayMapValueType(OA\AbstractAnnotation $schema, array $registry): ?string
    {
        $additionalProperties = $this->schemaAtKey($schema, 'additionalProperties');
        if ($additionalProperties === null) {
            return null;
        }

        $mapped = $this->mapOpenApiTypeToPhp($additionalProperties, $registry);
        if ($mapped['phpType'] !== self::PHP_TYPE_ARRAY) {
            return $this->normalizePhpDocIterableType($mapped['phpType']);
        }

        if ($mapped['arrayItemType'] !== null) {
            return 'list<' . $this->normalizePhpDocIterableType($mapped['arrayItemType']) . '>';
        }

        $nestedMapValueType = $this->arrayMapValueType($this->dereferenceSchema($additionalProperties, $registry), $registry);

        return $nestedMapValueType !== null ? 'array<string, ' . $nestedMapValueType . '>' : self::PHP_TYPE_MIXED;
    }

    private function normalizePhpDocIterableType(string $type): string
    {
        return implode('|', array_map(
            static fn (string $unionType): string => $unionType === self::PHP_TYPE_ARRAY ? 'array<string, mixed>' : $unionType,
            explode('|', $type),
        ));
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return array{properties: array<string, mixed>, required: list<string>}
     */
    private function resolveSchemaProperties(OA\AbstractAnnotation $schema, array $registry): array
    {
        $resolvedSchema = $this->dereferenceSchema($schema, $registry);
        $allOf = $this->schemaListAtKey($resolvedSchema, 'allOf');
        $properties = [];
        $required = $this->stringListAtKey($resolvedSchema, 'required');
        if ($allOf !== null) {
            foreach ($allOf as $partialSchema) {
                $partial = $this->resolveSchemaProperties($partialSchema, $registry);
                $properties = [...$properties, ...$partial['properties']];
                $required = [...$required, ...$partial['required']];
            }
        }

        return [
            'properties' => [...$properties, ...($this->arrayAtKey($resolvedSchema, 'properties') ?? [])],
            'required' => array_values(array_unique($required)),
        ];
    }

    /**
     * @param list<OA\Schema> $variants
     * @param list<OpenApiDtoProperty> $parameters
     * @param array<string, OA\Schema> $registry
     *
     * @return list<OpenApiDtoDefinition>
     */
    private function extractVariantRequestDtos(
        string $fallbackDtoName,
        OA\AbstractAnnotation $requestSchema,
        array $variants,
        array $parameters,
        array $registry,
        OA\AbstractAnnotation $operation,
    ): array {
        $definitions = [];
        $shared = $this->resolveSchemaProperties($requestSchema, $registry);

        foreach ($variants as $variant) {
            $resolvedVariant = $this->dereferenceSchema($variant, $registry);
            $resolvedProperties = $this->resolveSchemaProperties($variant, $registry);
            $title = $this->stringOrNull($this->get($variant, 'title'));
            $variantDtoName = $title !== null && $title !== '' ? $this->toPascalCase($title) : $fallbackDtoName;

            $variantProperties = [
                ...$shared['properties'],
                ...$resolvedProperties['properties'],
            ];
            $variantRequired = [
                ...$shared['required'],
                ...$resolvedProperties['required'],
            ];
            $extracted = $this->extractPropertiesFromSchema($variantProperties, array_values(array_unique($variantRequired)), $variantDtoName, $registry, $this->packageFromSchema($resolvedVariant) ?? $this->packageFromSchema($requestSchema));
            $properties = $this->mergeProperties($extracted['properties'], $parameters);

            if ($properties === []) {
                continue;
            }

            $definitions[] = new OpenApiDtoDefinition(
                $variantDtoName,
                $properties,
                $this->stringOrNull($this->get($resolvedVariant, 'description')) ?? $this->stringOrNull($this->get($operation, 'description')),
                $this->packageFromSchema($resolvedVariant) ?? $this->packageFromSchema($operation),
                type: OpenApiDtoType::Request,
            );
            $definitions = [...$definitions, ...$extracted['nestedDefinitions']];
        }

        return $definitions;
    }

    /**
     * @param array<string, OA\Schema> $registry
     *
     * @return array{phpType: string, arrayItemType: ?string, nullable: bool, unresolved: bool}
     */
    private function mapOpenApiTypeToPhp(OA\AbstractAnnotation $schema, array $registry): array
    {
        $ref = $this->refFromSchema($schema);
        if ($ref !== null) {
            $refName = $this->resolveRefName($ref);
            // Referenced schema is not part of the static schema set (e.g. it is generated at
            // runtime from a generic entity definition). Fall back to a plain array map.
            $resolvable = isset($registry[$refName]);
            $referencedSchema = $registry[$refName] ?? null;
            if ($referencedSchema !== null && !$this->hasDtoShape($referencedSchema)) {
                if ($this->nativeEnumType($referencedSchema) !== null) {
                    return [
                        'phpType' => $this->toPascalCase($refName),
                        'arrayItemType' => null,
                        'nullable' => false,
                        'unresolved' => false,
                    ];
                }

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
                fn (OA\Schema $variant): bool => !$this->isNullSchema($variant),
            ));

            $hasNull = \count($variants) !== \count($nonNullVariants);
            if (\count($nonNullVariants) === 1) {
                $mapped = $this->mapOpenApiTypeToPhp($nonNullVariants[0], $registry);

                return [
                    ...$mapped,
                    'nullable' => $hasNull || $mapped['nullable'],
                ];
            }

            $mappedVariants = array_map(
                fn (OA\Schema $variant): array => $this->mapOpenApiTypeToPhp($variant, $registry),
                $nonNullVariants,
            );
            $phpTypes = array_values(array_unique(array_column($mappedVariants, 'phpType')));

            if (\in_array(self::PHP_TYPE_MIXED, $phpTypes, true)) {
                return ['phpType' => self::PHP_TYPE_MIXED, 'arrayItemType' => null, 'nullable' => $hasNull, 'unresolved' => false];
            }

            return [
                'phpType' => implode('|', $phpTypes),
                'arrayItemType' => null,
                'nullable' => $hasNull || array_any($mappedVariants, static fn (array $mapped): bool => $mapped['nullable']),
                'unresolved' => array_any($mappedVariants, static fn (array $mapped): bool => $mapped['unresolved']),
            ];
        }

        $allOf = $this->schemaListAtKey($schema, 'allOf');
        if ($allOf !== null) {
            $title = $this->stringOrNull($this->get($schema, 'title'));
            if ($title !== null) {
                return [
                    'phpType' => $this->toPascalCase($title),
                    'arrayItemType' => null,
                    'nullable' => false,
                    'unresolved' => false,
                ];
            }

            if (\count($allOf) === 1) {
                return $this->mapOpenApiTypeToPhp($allOf[0], $registry);
            }

            foreach ($allOf as $partialSchema) {
                if (($this->get($partialSchema, '$ref') !== null) && \is_string($this->get($partialSchema, '$ref'))) {
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
     * @param array<string, OA\Schema> $registry
     */
    private function dereferenceSchema(OA\AbstractAnnotation $schema, array $registry): OA\AbstractAnnotation
    {
        $ref = $this->refFromSchema($schema);
        if ($ref === null) {
            return $schema;
        }

        if (!isset($registry[$this->resolveRefName($ref)])) {
            return $schema;
        }
        $resolved = $this->document->ref($ref);

        return $resolved instanceof OA\Schema ? $resolved : $schema;
    }

    /**
     * @return array{response: OA\AbstractAnnotation, statusCode: int}|null
     */
    private function successResponse(OA\AbstractAnnotation $operation): ?array
    {
        $responses = $this->arrayAtKey($operation, 'responses');
        if ($responses === null) {
            return null;
        }

        foreach (array_keys($responses) as $statusCode) {
            $statusCode = (string) $statusCode;
            if (!preg_match('/^2\\d{2}$/D', $statusCode)) {
                continue;
            }

            $response = $this->schemaAtKey($responses, $statusCode);
            if ($response === null) {
                continue;
            }

            return ['response' => $response, 'statusCode' => (int) $statusCode];
        }

        return null;
    }

    /**
     * @return list<OA\Schema>|null
     */
    private function variants(OA\AbstractAnnotation $schema): ?array
    {
        return $this->schemaListAtKey($schema, 'oneOf') ?? $this->schemaListAtKey($schema, 'anyOf');
    }

    private function singleAllOfReference(OA\AbstractAnnotation $schema): ?OA\AbstractAnnotation
    {
        if ($this->arrayAtKey($schema, 'properties') !== null || $this->stringListAtKey($schema, 'required') !== [] || $this->variants($schema) !== null) {
            return null;
        }

        $allOf = $this->schemaListAtKey($schema, 'allOf');
        if ($allOf === null || \count($allOf) !== 1) {
            return null;
        }

        $ref = $this->stringOrNull($this->get($allOf[0], '$ref'));
        if ($ref === null) {
            return null;
        }

        return $allOf[0];
    }

    private function packageFromSchema(OA\AbstractAnnotation $schema): ?string
    {
        return $this->stringOrNull($this->get($schema, OpenApiDtoGenerator::PACKAGE_EXTENSION));
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
     * @param list<OpenApiDtoDefinition> $componentDefinitions
     * @param list<OpenApiDtoDefinition> $operationDefinitions
     */
    private function assertNoComponentOperationNameCollisions(array $componentDefinitions, array $operationDefinitions): void
    {
        $componentNames = [];
        foreach ($componentDefinitions as $definition) {
            $componentNames[$definition->name] = true;
        }

        foreach ($operationDefinitions as $definition) {
            if (!isset($componentNames[$definition->name])) {
                continue;
            }

            throw FrameworkException::invalidArgumentException(\sprintf(
                'Component schema and operation generate the same DTO name "%s". Use unique names or mark the composition-only component with "%s": true.',
                $definition->name,
                OpenApiDtoGenerator::INLINE_EXTENSION,
            ));
        }
    }

    private function isInlineObject(OA\AbstractAnnotation $schema): bool
    {
        return $this->schemaType($schema) === 'object'
            && $this->arrayAtKey($schema, 'properties') !== null
            && !($this->get($schema, '$ref') !== null);
    }

    private function isInlineComponent(OA\AbstractAnnotation $schema): bool
    {
        return $this->get($schema, OpenApiDtoGenerator::INLINE_EXTENSION) === true;
    }

    private function hasDtoShape(OA\AbstractAnnotation $schema): bool
    {
        return $this->arrayAtKey($schema, 'properties') !== null
            || $this->schemaListAtKey($schema, 'allOf') !== null;
    }

    private function isNullSchema(OA\AbstractAnnotation $schema): bool
    {
        $type = $this->get($schema, 'type');
        if ($type === 'null') {
            return true;
        }

        if (!\is_array($type)) {
            return false;
        }

        return $type !== [] && array_values(array_filter($type, static fn (mixed $value): bool => $value !== 'null')) === [];
    }

    private function hasTypeNull(OA\AbstractAnnotation $schema): bool
    {
        $type = $this->get($schema, 'type');

        return \is_array($type) && \in_array('null', $type, true);
    }

    private function schemaType(OA\AbstractAnnotation $schema): ?string
    {
        $type = $this->get($schema, 'type');
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

    /**
     * OpenApiFileLoader merges identical component refs from Admin and Store API schemas into a list.
     */
    private function refFromSchema(OA\AbstractAnnotation $schema): ?string
    {
        $ref = $this->get($schema, '$ref');
        if (\is_string($ref)) {
            return $ref;
        }

        if (!\is_array($ref)) {
            return null;
        }

        $references = array_values(array_unique(array_filter($ref, 'is_string')));

        return \count($references) === 1 ? $references[0] : null;
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
     * @param list<OpenApiDtoProperty> ...$propertyLists
     *
     * @return list<OpenApiDtoProperty>
     */
    private function mergeProperties(array ...$propertyLists): array
    {
        $properties = [];
        $propertySources = [];

        foreach ($propertyLists as $propertyList) {
            foreach ($propertyList as $property) {
                if (isset($propertySources[$property->name])) {
                    throw FrameworkException::invalidArgumentException(
                        \sprintf('Properties "%s" and "%s" produce duplicate PHP property name "%s".', $propertySources[$property->name], $property->name, $property->name),
                    );
                }

                $propertySources[$property->name] = $property->name;
                $properties[] = $property;
            }
        }

        return $properties;
    }

    private function scalarValue(mixed $value): string|int|float|bool|null
    {
        return \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value) ? $value : null;
    }

    private function nativeEnumType(OA\AbstractAnnotation $schema): ?string
    {
        $values = $this->scalarEnum($this->get($schema, 'enum'));
        $type = $this->schemaType($schema);

        if ($values === null || !\in_array($type, ['string', 'integer'], true)) {
            return null;
        }

        $valueType = $type === 'string' ? 'string' : 'integer';
        foreach ($values as $value) {
            if (\gettype($value) !== $valueType) {
                return null;
            }
        }

        return $type === 'integer' ? 'int' : 'string';
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

    private function arrayItemMinLength(OA\AbstractAnnotation $schema): ?int
    {
        $items = $this->schemaAtKey($schema, 'items');

        return $items !== null ? $this->intOrNull($this->get($items, 'minLength')) : null;
    }

    private function arrayItemMaxLength(OA\AbstractAnnotation $schema): ?int
    {
        $items = $this->schemaAtKey($schema, 'items');

        return $items !== null ? $this->intOrNull($this->get($items, 'maxLength')) : null;
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
     * @param array<string, mixed> $schema
     */
    private function deserialize(array $schema): OA\OpenApi
    {
        $document = (new OpenApiDtoSerializer())->deserialize(json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), OA\OpenApi::class);
        if (!$document instanceof OA\OpenApi) {
            throw FrameworkException::invalidArgumentException('Expected an OpenAPI document.');
        }
        $this->document = $document;

        return $document;
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed>|null $source
     */
    private function get(OA\AbstractAnnotation|array|null $source, string $key): mixed
    {
        if ($source === null) {
            return null;
        }
        if (\is_array($source)) {
            return $source[$key] ?? null;
        }
        if (str_starts_with($key, 'x-')) {
            return \is_array($source->x) ? ($source->x[substr($key, 2)] ?? null) : null;
        }
        $key = $key === '$ref' ? 'ref' : $key;
        $value = get_object_vars($source)[$key] ?? null;
        if (Undefined::isDefault($value)) {
            return null;
        }
        if ($source instanceof OA\Operation && $key === 'responses' && \is_array($value)) {
            $responses = [];
            foreach ($value as $response) {
                if ($response instanceof OA\Response) {
                    $responses[$response->response] = $response;
                }
            }

            return $responses;
        }
        if (\in_array($key, ['default', 'const'], true)) {
            return $this->jsonValue($value);
        }

        return $value;
    }

    private function hasDefault(OA\AbstractAnnotation $schema): bool
    {
        return $schema instanceof OA\Schema && !Undefined::isDefault($schema->default);
    }

    private function jsonValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        return \is_array($value) ? array_map($this->jsonValue(...), $value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function children(OA\AbstractAnnotation $node): array
    {
        return array_filter(get_object_vars($node), static fn (mixed $value, string $key): bool => !str_starts_with($key, '_') && !Undefined::isDefault($value), \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed> $source
     */
    private function schemaAtKey(OA\AbstractAnnotation|array $source, string $key): ?OA\AbstractAnnotation
    {
        $value = $this->get($source, $key);

        return $value instanceof OA\AbstractAnnotation ? $value : null;
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed> $source
     *
     * @return array<array-key, mixed>|null
     */
    private function arrayAtKey(OA\AbstractAnnotation|array $source, string $key): ?array
    {
        $value = $this->get($source, $key);

        return \is_array($value) ? $value : null;
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed> $source
     * @param list<string> $path
     */
    private function atPath(OA\AbstractAnnotation|array $source, array $path): mixed
    {
        $current = $source;
        foreach ($path as $key) {
            if (!\is_array($current) && !$current instanceof OA\AbstractAnnotation) {
                return null;
            }
            $current = $this->get($current, $key);
        }

        return $current;
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed> $source
     * @param list<string> $path
     *
     * @return array<array-key, mixed>|null
     */
    private function arrayAtPath(OA\AbstractAnnotation|array $source, array $path): ?array
    {
        $value = $this->atPath($source, $path);

        return \is_array($value) ? $value : null;
    }

    /**
     * @param OA\AbstractAnnotation|array<array-key, mixed> $source
     * @param list<string> $path
     */
    private function nodeAtPath(OA\AbstractAnnotation|array $source, array $path): ?OA\AbstractAnnotation
    {
        $value = $this->atPath($source, $path);

        return $value instanceof OA\AbstractAnnotation ? $value : null;
    }

    /**
     * @return list<OA\Schema>|null
     */
    private function schemaListAtKey(OA\AbstractAnnotation $source, string $key): ?array
    {
        $value = $this->arrayAtKey($source, $key);
        if ($value === null || !\array_is_list($value)) {
            return null;
        }
        foreach ($value as $item) {
            if (!$item instanceof OA\Schema) {
                return null;
            }
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringListAtKey(OA\AbstractAnnotation $source, string $key): array
    {
        return array_values(array_filter($this->arrayAtKey($source, $key) ?? [], 'is_string'));
    }
}
