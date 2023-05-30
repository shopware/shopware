<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Delete;
use OpenApi\Annotations\Get;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Patch;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response as OpenApiResponse;
use OpenApi\Annotations\Tag;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class OpenApiPathBuilder
{
    /**
     * @return PathItem[]
     */
    public function getPathActions(EntityDefinition $definition, string $path): array
    {
        $paths = [];
        $paths[$path] = new PathItem([
            'path' => $path,
        ]);
        $paths[$path]->get = $this->getListingPath($definition, $path);

        $paths[$path . '/{id}'] = new PathItem([
            'path' => $path . '/{id}',
        ]);
        $paths[$path . '/{id}']->get = $this->getDetailPath($definition);

        if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            return $paths;
        }

        $paths[$path]->post = $this->getCreatePath($definition);
        $paths[$path . '/{id}']->patch = $this->getUpdatePath($definition);
        $paths[$path . '/{id}']->delete = $this->getDeletePath($definition);

        return $paths;
    }

    public function getTag(EntityDefinition $definition): Tag
    {
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

        return new Tag(['name' => $humanReadableName, 'description' => 'The endpoint for operations on ' . $humanReadableName]);
    }

    private function getListingPath(EntityDefinition $definition, string $path): Get
    {
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return new Get([
            'summary' => 'List with basic information of ' . $humanReadableName . ' resources',
            'description' => $definition->since() ? 'Available since: ' . $definition->since() : '',
            'tags' => [$humanReadableName],
            'parameters' => $this->getDefaultListingParameter(),
            'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()) . 'List',
            'responses' => [
                Response::HTTP_OK => new OpenApiResponse([
                    'response' => Response::HTTP_OK,
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
                ]),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
            ],
        ]);
    }

    private function getDetailPath(EntityDefinition $definition): Get
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return new Get([
            'summary' => 'Detailed information about a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource',
            'description' => $definition->since() ? 'Available since: ' . $definition->since() : '',
            'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()),
            'tags' => [$this->convertToHumanReadable($definition->getEntityName())],
            'parameters' => [$this->getIdParameter($definition)],
            'responses' => [
                Response::HTTP_OK => $this->getDetailResponse($schemaName),
                Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
            ],
        ]);
    }

    private function getCreatePath(EntityDefinition $definition): Post
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return new Post([
            'summary' => 'Create a new ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resources',
            'description' => $definition->since() ? 'Available since: ' . $definition->since() : '',
            'tags' => [$this->convertToHumanReadable($definition->getEntityName())],
            'operationId' => 'create' . $this->convertToOperationId($definition->getEntityName()),
            'parameters' => [
                new Parameter([
                    'name' => '_response',
                    'in' => 'query',
                    'schema' => ['type' => 'string', 'enum' => ['basic', 'detail']],
                    'description' => 'Data format for response. Empty if none is provided.',
                ]),
            ],
            'requestBody' => [
                'content' => [
                    'application/vnd.api+json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    '$ref' => '#/components/schemas/' . $schemaName,
                                ],
                                'included' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/resource'],
                                    'uniqueItems' => true,
                                ],
                            ],
                        ],
                    ],
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaName,
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_CREATED => $this->getDetailResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef((string) Response::HTTP_BAD_REQUEST),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
            ],
        ]);
    }

    private function getUpdatePath(EntityDefinition $definition): Patch
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        return new Patch([
            'summary' => 'Partially update information about a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource',
            'description' => $definition->since() ? 'Available since: ' . $definition->since() : '',
            'operationId' => 'update' . $this->convertToOperationId($definition->getEntityName()),
            'tags' => [$this->convertToHumanReadable($definition->getEntityName())],
            'parameters' => [$this->getIdParameter($definition), $this->getResponseDataParameter()],
            'requestBody' => [
                'description' => 'Partially update information about a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource.',
                'content' => [
                    'application/vnd.api+json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    '$ref' => '#/components/schemas/' . $schemaName,
                                ],
                                'included' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/resource'],
                                    'uniqueItems' => true,
                                ],
                            ],
                        ],
                    ],
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaName,
                        ],
                    ],
                ],
            ],
            'responses' => [
                Response::HTTP_OK => $this->getDetailResponse($schemaName),
                Response::HTTP_BAD_REQUEST => $this->getResponseRef((string) Response::HTTP_BAD_REQUEST),
                Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
            ],
        ]);
    }

    private function getDeletePath(EntityDefinition $definition): Delete
    {
        return new Delete([
            'operationId' => 'delete' . $this->convertToOperationId($definition->getEntityName()),
            'description' => $definition->since() ? 'Available since: ' . $definition->since() : '',
            'summary' => 'Delete a ' . $this->convertToHumanReadable($definition->getEntityName()) . ' resource',
            'tags' => [$this->convertToHumanReadable($definition->getEntityName())],
            'parameters' => [$this->getIdParameter($definition), $this->getResponseDataParameter()],
            'responses' => [
                Response::HTTP_NO_CONTENT => $this->getResponseRef((string) Response::HTTP_NO_CONTENT),
                Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
            ],
        ]);
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

    private function getDefaultListingParameter(): array
    {
        return [
            new Parameter([
                'name' => 'limit',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'Max amount of resources to be returned in a page',
            ]),
            new Parameter([
                'name' => 'page',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'The page to be returned',
            ]),
            new Parameter([
                'name' => 'query',
                'in' => 'query',
                'schema' => [
                    'type' => 'string',
                ],
                'description' => 'Encoded SwagQL in JSON',
            ]),
        ];
    }

    private function getDetailResponse(string $schemaName): OpenApiResponse
    {
        return new OpenApiResponse([
            'response' => Response::HTTP_OK,
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
                        '$ref' => '#/components/schemas/' . $schemaName,
                    ],
                ],
            ],
        ]);
    }

    private function getResponseRef(string $responseName): OpenApiResponse
    {
        return new OpenApiResponse([
            'response' => $responseName,
            'ref' => '#/components/responses/' . $responseName,
        ]);
    }

    private function getResponseDataParameter(): Parameter
    {
        $responseDataParameter = new Parameter([
            'name' => '_response',
            'in' => 'query',
            'schema' => [
                'type' => 'string',
            ],
            'allowEmptyValue' => true,
            'description' => 'Data format for response. Empty if none is provided.',
        ]);

        return $responseDataParameter;
    }

    private function getIdParameter(EntityDefinition $definition): Parameter
    {
        $idParameter = new Parameter([
            'name' => 'id',
            'in' => 'path',
            'schema' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$'],
            'description' => 'Identifier for the ' . $definition->getEntityName(),
            'required' => true,
        ]);

        return $idParameter;
    }

    private function snakeCaseToCamelCase(string $input): string
    {
        return str_replace('_', '', ucwords($input, '_'));
    }
}
