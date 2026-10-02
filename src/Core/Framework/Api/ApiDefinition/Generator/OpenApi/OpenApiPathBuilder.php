<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Delete;
use OpenApi\Annotations\Get;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Patch;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response as OpenApiResponse;
use OpenApi\Annotations\Tag;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Builds the CRUD path items of the Admin API for an entity definition.
 *
 * @phpstan-type OpenApiOperation array{tags: list<string>, summary: string, description: string, operationId: string, parameters?: list<array<string, mixed>>, requestBody?: array<string, mixed>, responses: array<int, array<string, mixed>>}
 * @phpstan-type OpenApiPathItem array<'get'|'post'|'patch'|'delete', OpenApiOperation>
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class OpenApiPathBuilder
{
    private const EXPERIMENTAL_ANNOTATION_NAME = 'experimental';

    private const EXPERIMENTAL_SUMMARY = ' Experimental API, not part of our backwards compatibility promise, thus this API can introduce breaking changes at any time.';

    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    /**
     * @internal
     */
    public function __construct()
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter(null, false);
    }

    /**
     * @internal
     *
     * @return array<string, OpenApiPathItem> path items keyed by their path
     */
    public function createPathItems(EntityDefinition $definition, string $path): array
    {
        $pathItems = [
            $path => ['get' => $this->getListingPath($definition, $path)],
            '/search' . $path => ['post' => $this->getSearchPath($definition)],
            $path . '/{id}' => ['get' => $this->getDetailPath($definition)],
            '/aggregate' . $path => ['post' => $this->getAggregatePath($definition)],
        ];

        if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            return $pathItems;
        }

        $pathItems[$path]['post'] = $this->getCreatePath($definition);
        $pathItems[$path . '/{id}']['delete'] = $this->getDeletePath($definition);
        $pathItems[$path . '/{id}']['patch'] = $this->getUpdatePath($definition);

        return $pathItems;
    }

    /**
     * @internal
     *
     * @return array{name: string, description: string}
     */
    public function createTag(EntityDefinition $definition): array
    {
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

        return ['name' => $humanReadableName, 'description' => 'The endpoint for operations on ' . $humanReadableName];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, the class becomes internal
     *
     * @return PathItem[]
     */
    public function getPathActions(EntityDefinition $definition, string $path): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $pathItems = [];

        foreach ($this->createPathItems($definition, $path) as $itemPath => $operations) {
            $annotations = [];

            foreach ($operations as $method => $operation) {
                $annotations[$method] = $this->createOperationAnnotation($method, $operation);
            }

            $pathItems[$itemPath] = new PathItem(['path' => $itemPath] + $annotations);
        }

        return $pathItems;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, the class becomes internal
     */
    public function getTag(EntityDefinition $definition): Tag
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return new Tag($this->createTag($definition));
    }

    /**
     * @param 'get'|'post'|'patch'|'delete' $method
     * @param OpenApiOperation $operation
     */
    private function createOperationAnnotation(string $method, array $operation): Operation
    {
        $responses = [];
        foreach ($operation['responses'] as $statusCode => $response) {
            if (isset($response['$ref'])) {
                $response = ['ref' => $response['$ref']];
            }

            $responses[] = new OpenApiResponse(['response' => $statusCode] + $response);
        }

        $operation['responses'] = $responses;

        return match ($method) {
            'get' => new Get($operation),
            'post' => new Post($operation),
            'patch' => new Patch($operation),
            'delete' => new Delete($operation),
        };
    }

    /**
     * @return OpenApiOperation
     */
    private function getListingPath(EntityDefinition $definition, string $path): array
    {
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'List with basic information of ' . $humanReadableName . ' resources.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()) . 'List',
            'parameters' => $this->getDefaultListingParameter(),
            'responses' => [
                Response::HTTP_OK => [
                    'description' => 'List of ' . $humanReadableName . ' resources.',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/success'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => [
                                                'allOf' => [
                                                    ['$ref' => '#/components/schemas/data'],
                                                    [
                                                        'type' => 'array',
                                                        'items' => [
                                                            '$ref' => '#/components/schemas/' . $schemaName,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'links' => [
                                                'allOf' => [
                                                    ['$ref' => '#/components/schemas/pagination'],
                                                    [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'first' => ['example' => $path . '?limit=25'],
                                                            'last' => ['example' => $path . '?limit=25&page=11'],
                                                            'next' => ['example' => $path . '?limit=25&page=4'],
                                                            'prev' => ['example' => $path . '?limit=25&page=2'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'total' => ['type' => 'integer'],
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/' . $schemaName,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getDetailPath(EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Detailed information about a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [$this->getIdParameter($definition)],
            'responses' => [
                Response::HTTP_OK => $this->getDetailResponse($schemaName),
                Response::HTTP_NOT_FOUND => $this->getResponseRef(Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getCreatePath(EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Create a new ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resources.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'create' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [
                [
                    'name' => '_response',
                    'in' => 'query',
                    'description' => 'Data format for response. Empty if none is provided.',
                    'schema' => ['type' => 'string', 'enum' => ['basic', 'detail']],
                ],
            ],
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaName,
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_OK => $this->getDetailResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef(Response::HTTP_BAD_REQUEST),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getUpdatePath(EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Partially update information about a ' . $humanReadableName . ' resource.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'update' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [$this->getIdParameter($definition), $this->getResponseDataParameter()],
            'requestBody' => [
                'description' => 'Partially update information about a ' . $humanReadableName . ' resource.',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaName,
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_OK => $this->getDetailResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef(Response::HTTP_BAD_REQUEST),
                Response::HTTP_NOT_FOUND => $this->getResponseRef(Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getDeletePath(EntityDefinition $definition): array
    {
        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Delete a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'delete' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [$this->getIdParameter($definition), $this->getResponseDataParameter()],
            'responses' => [
                Response::HTTP_NO_CONTENT => $this->getResponseRef(Response::HTTP_NO_CONTENT),
                Response::HTTP_NOT_FOUND => $this->getResponseRef(Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getSearchPath(EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Search for the ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resources.' . $this->getExperimentalSummary($definition),
            'description' => $this->getSinceDescription($definition),
            'operationId' => 'search' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [
                [
                    'name' => 'sw-include-search-info',
                    'in' => 'header',
                    'description' => 'Controls whether API search information is included in the response. Default is 1 (enabled), will be 0 (disabled) in the next major version.',
                    'schema' => [
                        'type' => 'string',
                        'enum' => ['0', '1'],
                        'default' => '1',
                    ],
                ],
            ],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Criteria',
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_OK => $this->getListResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef(Response::HTTP_BAD_REQUEST),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return OpenApiOperation
     */
    private function getAggregatePath(EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        $since = $definition->since();

        // In this version we added this aggregate api
        if ($since === null || version_compare('6.6.10.0', $since, '>=')) {
            $since = '6.6.10.0';
        }

        return [
            'tags' => $this->getTags($definition),
            'summary' => 'Aggregate for the ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resources.' . $this->getExperimentalSummary($definition),
            'description' => 'Available since: ' . $since,
            'operationId' => 'aggregate' . $this->convertToOperationId($definition->getEntityName()),
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'aggregations' => [
                                    'type' => 'array',
                                    'items' => [
                                        '$ref' => '#/components/schemas/Aggregation',
                                    ],
                                ],
                            ],
                            'required' => ['aggregations'],
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_OK => $this->getListResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef(Response::HTTP_BAD_REQUEST),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef(Response::HTTP_UNAUTHORIZED),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function getTags(EntityDefinition $definition): array
    {
        $tags = [$this->convertToHumanReadable($definition->getEntityName())];

        if ($this->isExperimental($definition)) {
            $tags[] = 'Experimental';
        }

        return $tags;
    }

    private function getExperimentalSummary(EntityDefinition $definition): string
    {
        return $this->isExperimental($definition) ? self::EXPERIMENTAL_SUMMARY : '';
    }

    private function getSinceDescription(EntityDefinition $definition): string
    {
        return $definition->since() ? 'Available since: ' . $definition->since() : '';
    }

    private function convertToHumanReadable(string $name): string
    {
        $nameParts = array_map('ucfirst', explode('_', $name));

        return implode(' ', $nameParts);
    }

    private function convertToOperationId(string $name): string
    {
        $name = ucfirst($this->convertToHumanReadable($name));

        return str_replace(' ', '', $name);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDefaultListingParameter(): array
    {
        return [
            [
                'name' => 'limit',
                'in' => 'query',
                'description' => 'Max amount of resources to be returned in a page',
                'schema' => [
                    'type' => 'integer',
                ],
            ],
            [
                'name' => 'page',
                'in' => 'query',
                'description' => 'The page to be returned',
                'schema' => [
                    'type' => 'integer',
                ],
            ],
            [
                'name' => 'query',
                'in' => 'query',
                'description' => 'Encoded SwagQL in JSON',
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDetailResponse(string $schemaName): array
    {
        return [
            'description' => 'Detail of ' . $schemaName,
            'content' => [
                'application/vnd.api+json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/success'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        '$ref' => '#/components/schemas/' . $schemaName,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['data'],
                        'properties' => [
                            'data' => [
                                '$ref' => '#/components/schemas/' . $schemaName,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getListResponse(string $schemaName): array
    {
        return [
            'description' => 'List of ' . $schemaName,
            'content' => [
                'application/vnd.api+json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/success'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/' . $schemaName,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'data' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/' . $schemaName,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{'$ref': string}
     */
    private function getResponseRef(int $statusCode): array
    {
        return ['$ref' => '#/components/responses/' . $statusCode];
    }

    /**
     * @return array<string, mixed>
     */
    private function getResponseDataParameter(): array
    {
        return [
            'name' => '_response',
            'in' => 'query',
            'description' => 'Data format for response. Empty if none is provided.',
            'allowEmptyValue' => true,
            'schema' => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getIdParameter(EntityDefinition $definition): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'description' => 'Identifier for the ' . $definition->getEntityName(),
            'required' => true,
            'schema' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$'],
        ];
    }

    private function snakeCaseToCamelCase(string $input): string
    {
        return $this->converter->denormalize($input);
    }

    private function isExperimental(EntityDefinition $definition): bool
    {
        $reflection = new \ReflectionClass($definition);

        return str_contains($reflection->getDocComment() ?: '', '@' . self::EXPERIMENTAL_ANNOTATION_NAME);
    }
}
