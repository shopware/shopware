<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Response as OpenApiResponse;
use OpenApi\Annotations\Schema;
use OpenApi\Annotations\SecurityScheme;
use OpenApi\Annotations\Server;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class OpenApiSchemaBuilder
{
    public function enrich(OpenApi $openApi, bool $forSalesChannel, int $version): void
    {
        $openApi->merge($this->createServers($forSalesChannel, $version));
        $openApi->info = $this->createInfo($forSalesChannel, $version);

        /** @var array|string $security */
        $security = $openApi->security;
        $openApi->security = array_merge(is_array($security) ? $security : [], $this->createSecurity($forSalesChannel));

        if (!$openApi->components instanceof Components) {
            $openApi->components = new Components([]);
        }

        $this->enrichComponents($openApi->components, $forSalesChannel);
    }

    /**
     * @return Server[]
     */
    private function createServers(bool $forSalesChannel, int $version): array
    {
        $url = $_SERVER['APP_URL'] ?? '';

        return [
            new Server(['url' => rtrim($url, '/') . ($forSalesChannel ? '/sales-channel-api/v' : '/api/v') . $version]),
        ];
    }

    private function createInfo(bool $forSalesChannel, int $version): Info
    {
        return new Info([
            'title' => 'Shopware ' . ($forSalesChannel ? 'Sales-Channel' : 'Management') . ' API',
            'version' => $version,
        ]);
    }

    private function createSecurity(bool $forSalesChannel): array
    {
        if ($forSalesChannel) {
            return ['ApiKey' => []];
        }

        return ['oAuth' => ['write']];
    }

    private function enrichComponents(Components $components, bool $forSalesChannel): void
    {
        $components->merge($this->getDefaultSchemas());
        $components->merge($this->createSecurityScheme($forSalesChannel));
        $components->merge($this->createDefaultResponses());
    }

    /**
     * @return Schema[]
     */
    private function getDefaultSchemas(): array
    {
        $defaults = [
            'success' => new Schema([
                'schema' => 'success',
                'type' => 'object',
                'required' => ['data'],
                'additionalProperties' => false,
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                    'links' => [
                        'description' => 'Link members related to the primary data.',
                        'property' => 'links',
                        'allOf' => [
                            ['$ref' => '#/components/schemas/links'],
                            ['$ref' => '#/components/schemas/pagination'],
                        ],
                    ],
                    'data' => ['$ref' => '#/components/schemas/data', 'property' => 'data'],
                    'included' => [
                        'description' => 'To reduce the number of HTTP requests, servers **MAY** allow responses that include related resources along with the requested primary resources. Such responses are called "compound documents".',
                        'type' => 'array',
                        'property' => 'included',
                        'items' => ['$ref' => '#/components/schemas/resource'],
                        'uniqueItems' => true,
                    ],
                ],
            ]),
            'failure' => new Schema([
                'schema' => 'failure',
                'type' => 'object',
                'required' => ['errors'],
                'additionalProperties' => false,
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'errors' => [
                        'type' => 'array',
                        'property' => 'errors',
                        'items' => ['$ref' => '#/components/schemas/error'],
                        'uniqueItems' => true,
                    ],
                ],
            ]),
            'info' => new Schema([
                'schema' => 'info',
                'type' => 'object',
                'required' => ['meta'],
                'properties' => [
                    'meta' => ['ref' => '#/components/schemas/meta', 'property' => 'meta'],
                    'links' => ['ref' => '#/components/schemas/links', 'property' => 'links'],
                    'jsonapi' => ['ref' => '#/components/schemas/jsonapi', 'property' => 'jsonapi'],
                ],
            ]),
            'meta' => new Schema([
                'schema' => 'meta',
                'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'data' => new Schema([
                'schema' => 'data',
                'description' => 'The document\'s "primary data" is a representation of the resource or collection of resources targeted by a request.',
                'oneOf' => [
                    ['$ref' => '#/components/schemas/resource'],
                    [
                        'description' => 'An array of resource objects, an array of resource identifier objects, or an empty array ([]), for requests that target resource collections.',
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/resource',
                        ],
                        'uniqueItems' => true,
                    ],
                ],
            ]),
            'resource' => new Schema([
                'schema' => 'resource',
                'description' => '"Resource objects" appear in a JSON API document to represent resources.',
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'property' => 'type'],
                    'id' => ['type' => 'string', 'property' => 'id'],
                    'attributes' => ['$ref' => '#/components/schemas/attributes', 'property' => 'attributes'],
                    'relationships' => ['$ref' => '#/components/schemas/relationships', 'property' => 'relationships'],
                    'links' => ['$ref' => '#/components/schemas/links', 'property' => 'links'],
                    'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                ],
            ]),
            'relationshipLinks' => new Schema([
                'schema' => 'relationshipLinks',
                'description' => 'A resource object **MAY** contain references to other resource objects ("relationships"). Relationships may be to-one or to-many. Relationships can be specified by including a member in a resource\'s links object.',
                'type' => 'object',
                'additionalProperties' => true,
                'properties' => [
                    'self' => [
                        'property' => 'self',
                        'description' => 'A `self` member, whose value is a URL for the relationship itself (a "relationship URL"). This URL allows the client to directly manipulate the relationship. For example, it would allow a client to remove an `author` from an `article` without deleting the people resource itself.',
                        '$ref' => '#/components/schemas/link',
                    ],
                    'related' => ['$ref' => '#/components/schemas/link', 'property' => 'related'],
                ],
            ]),
            'links' => new Schema([
                'schema' => 'links',
                'type' => 'object',
                'additionalProperties' => [
                    '$ref' => '#/components/schemas/link',
                ],
            ]),
            'link' => new Schema([
                'schema' => 'link',
                'description' => 'A link **MUST** be represented as either: a string containing the link\'s URL or a link object.',
                'oneOf' => [
                    [
                        'description' => 'A string containing the link\'s URL.',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    [
                        'type' => 'object',
                        'required' => ['href'],
                        'properties' => [
                            'href' => [
                                'property' => 'href',
                                'description' => 'A string containing the link\'s URL.',
                                'type' => 'string',
                                'format' => 'uri-reference',
                            ],
                            'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                        ],
                    ],
                ],
            ]),
            'attributes' => new Schema([
                'schema' => 'attributes',
                'description' => 'Members of the attributes object ("attributes") represent information about the resource object in which it\'s defined.',
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'relationships' => new Schema([
                'schema' => 'relationships',
                'description' => 'Members of the relationships object ("relationships") represent references from the resource object in which it\'s defined to other resource objects.',
                'type' => 'object',
                'anyOf' => [
                    ['required' => ['data']],
                    ['required' => ['meta']],
                    ['required' => ['links']],
                    [
                        'type' => 'object',
                        'properties' => [
                            'links' => ['$ref' => '#/components/schemas/relationshipLinks', 'property' => 'links'],
                            'data' => [
                                'property' => 'data',
                                'description' => 'Member, whose value represents "resource linkage".',
                                'oneOf' => [
                                    ['$ref' => '#/components/schemas/relationshipToOne'],
                                    ['$ref' => '#/components/schemas/relationshipToMany'],
                                ],
                            ],
                        ],
                    ],
                ],
                'additionalProperties' => false,
            ]),
            'relationshipToOne' => new Schema([
                'schema' => 'relationshipToOne',
                'description' => 'References to other resource objects in a to-one ("relationship"). Relationships can be specified by including a member in a resource\'s links object.',
                'ref' => '#/components/schemas/linkage',
            ]),
            'relationshipToMany' => new Schema([
                'schema' => 'relationshipToMany',
                'description' => 'An array of objects each containing \"type\" and \"id\" members for to-many relationships.',
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/linkage',
                ],
                'uniqueItems' => true,
            ]),
            'linkage' => new Schema([
                'schema' => 'linkage',
                'description' => 'The "type" and "id" to non-empty members.',
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'property' => 'type'],
                    'id' => ['type' => 'string', 'format' => 'uuid', 'property' => 'id'],
                    'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                ],
                'additionalProperties' => false,
            ]),
            'pagination' => new Schema([
                'schema' => 'pagination',
                'type' => 'object',
                'properties' => [
                    'first' => [
                        'description' => 'The first page of data',
                        'property' => 'first',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'last' => [
                        'description' => 'The last page of data',
                        'property' => 'last',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'prev' => [
                        'description' => 'The previous page of data',
                        'property' => 'prev',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'next' => [
                        'description' => 'The next page of data',
                        'property' => 'next',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                ],
            ]),
            'jsonapi' => new Schema([
                'schema' => 'jsonapi',
                'description' => 'An object describing the server\'s implementation',
                'type' => 'object',
                'properties' => [
                    'version' => ['type' => 'string', 'property' => 'version'],
                    'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                ],
                'additionalProperties' => false,
            ]),
            'error' => new Schema([
                'schema' => 'error',
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'A unique identifier for this particular occurrence of the problem.', 'property' => 'id'],
                    'links' => ['$ref' => '#/components/schemas/links', 'property' => 'links'],
                    'status' => ['type' => 'string', 'description' => 'The HTTP status code applicable to this problem, expressed as a string value.', 'property' => 'status'],
                    'code' => ['type' => 'string', 'description' => 'An application-specific error code, expressed as a string value.', 'property' => 'code'],
                    'title' => ['type' => 'string', 'description' => 'A short, human-readable summary of the problem. It **SHOULD NOT** change from occurrence to occurrence of the problem, except for purposes of localization.', 'property' => 'title'],
                    'detail' => ['type' => 'string', 'description' => 'A human-readable explanation specific to this occurrence of the problem.', 'property' => 'detail'],
                    'source' => [
                        'property' => 'source',
                        'type' => 'object',
                        'properties' => [
                            'pointer' => ['type' => 'string', 'description' => 'A JSON Pointer [RFC6901] to the associated entity in the request document [e.g. "/data" for a primary data object, or "/data/attributes/title" for a specific attribute].', 'property' => 'pointer'],
                            'parameter' => ['type' => 'string', 'description' => 'A string indicating which query parameter caused the error.', 'property' => 'parameter'],
                        ],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/meta', 'property' => 'meta'],
                ],
                'additionalProperties' => false,
            ]),
        ];

        return $defaults;
    }

    /**
     * @return SecurityScheme[]
     */
    private function createSecurityScheme(bool $forSalesChannel): array
    {
        if ($forSalesChannel) {
            return [
                'ApiKey' => new SecurityScheme([
                    'securityScheme' => 'ApiKey',
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => PlatformRequest::HEADER_ACCESS_KEY,
                ]),
            ];
        }

        $url = $_SERVER['APP_URL'] ?? '';

        return [
            'oAuth' => new SecurityScheme([
                'securityScheme' => 'oAuth',
                'type' => 'oauth2',
                'description' => 'Authentication API',
                'flows' => [
                    'password' => [
                        'tokenUrl' => $url . '/api/oauth/token',
                        'scopes' => [
                            'write' => 'Full write access',
                        ],
                    ],
                    'clientCredentials' => [
                        'tokenUrl' => $url . '/api/oauth/token',
                        'scopes' => [
                            'write' => 'Full write access',
                        ],
                    ],
                ],
            ]),
        ];
    }

    /**
     * @return OpenApiResponse[]
     */
    private function createDefaultResponses(): array
    {
        return [
            Response::HTTP_NOT_FOUND => $this->createErrorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'Resource with given parameter was not found.'),
            Response::HTTP_UNAUTHORIZED => $this->createErrorResponse(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Authorization information is missing or invalid.'),
            Response::HTTP_BAD_REQUEST => $this->createErrorResponse(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Bad parameters for this endpoint. See documentation for the correct ones.'),
            Response::HTTP_NO_CONTENT => new OpenApiResponse(['description' => 'The resource was deleted successfully.', 'response' => Response::HTTP_NO_CONTENT]),
        ];
    }

    private function createErrorResponse(int $statusCode, string $title, string $description): OpenApiResponse
    {
        $schema = new Schema([
            'schema' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/failure'],
                    [
                        'example' => [
                            'errors' => [
                                [
                                    'status' => (string) $statusCode,
                                    'title' => $title,
                                    'description' => $description,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return new OpenApiResponse([
            'response' => $statusCode,
            'description' => $title,
            'content' => [
                'application/vnd.api+json' => new MediaType([
                    'mediaType' => 'application/vnd.api+json',
                    'schema' => $schema,
                ]),
                'application/json' => new MediaType([
                    'mediaType' => 'application/json',
                    'schema' => $schema,
                ]),
            ],
        ]);
    }
}
