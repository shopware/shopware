<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Response as OpenApiResponse;
use OpenApi\Annotations\Schema;
use OpenApi\Annotations\SecurityScheme;
use OpenApi\Annotations\Server;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class OpenApiSchemaBuilder
{
    final public const API = [
        DefinitionService::API => [
            'name' => 'Admin API',
            'url' => '/api',
            'apiKey' => false,
        ],
        DefinitionService::STORE_API => [
            'name' => 'Store API',
            'url' => '/store-api',
            'apiKey' => true,
        ],
    ];

    /**
     * @internal
     */
    public function __construct(private readonly string $version)
    {
    }

    public function enrich(OpenApi $openApi, string $api): void
    {
        $openApi->merge($this->createServers($api));
        $openApi->info = $this->createInfo($api, $this->version);

        /** @var array|string $security */
        $security = $openApi->security;
        $openApi->security = [array_merge(\is_array($security) ? $security : [], $this->createSecurity($api))];

        if (!$openApi->components instanceof Components) {
            $openApi->components = new Components([]);
        }

        $this->enrichComponents($openApi->components, $api);
    }

    /**
     * @return Server[]
     */
    private function createServers(string $api): array
    {
        $url = (string) EnvironmentHelper::getVariable('APP_URL', '');

        return [
            new Server(['url' => rtrim($url, '/') . self::API[$api]['url']]),
        ];
    }

    private function createInfo(string $api, string $version): Info
    {
        return new Info([
            'title' => 'Shopware ' . self::API[$api]['name'],
            'version' => $version,
            'description' => <<<'EOF'
This endpoint reference contains an overview of all endpoints comprising the Shopware Admin API.

For a better overview, all CRUD-endpoints are hidden by default. If you want to show also CRUD-endpoints
add the query parameter `type=jsonapi`.
EOF
        ]);
    }

    private function createSecurity(string $api): array
    {
        if (self::API[$api]['apiKey']) {
            return ['ApiKey' => []];
        }

        return ['oAuth' => ['write']];
    }

    private function enrichComponents(Components $components, string $api): void
    {
        $components->merge($this->getDefaultSchemas());
        $components->merge($this->createSecurityScheme($api));
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
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => [
                        'description' => 'Link members related to the primary data.',
                        'allOf' => [
                            ['$ref' => '#/components/schemas/links'],
                            ['$ref' => '#/components/schemas/pagination'],
                        ],
                    ],
                    'data' => ['$ref' => '#/components/schemas/data'],
                    'included' => [
                        'description' => 'To reduce the number of HTTP requests, servers **MAY** allow responses that include related resources along with the requested primary resources. Such responses are called "compound documents".',
                        'type' => 'array',
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
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'jsonapi' => ['$ref' => '#/components/schemas/jsonapi'],
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
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                    'attributes' => ['$ref' => '#/components/schemas/attributes'],
                    'relationships' => ['$ref' => '#/components/schemas/relationships'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
            ]),
            'relationshipLinks' => new Schema([
                'schema' => 'relationshipLinks',
                'description' => 'A resource object **MAY** contain references to other resource objects ("relationships"). Relationships may be to-one or to-many. Relationships can be specified by including a member in a resource\'s links object.',
                'type' => 'object',
                'additionalProperties' => true,
                'properties' => [
                    new Property([
                        'property' => 'self',
                        'allOf' => [
                            new Schema([
                                'description' => 'A `self` member, whose value is a URL for the relationship itself (a "relationship URL"). This URL allows the client to directly manipulate the relationship. For example, it would allow a client to remove an `author` from an `article` without deleting the people resource itself.',
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                ],
                            ]),
                            new Schema([
                                'ref' => '#/components/schemas/link',
                            ]),
                        ],
                    ]),
                    new Property([
                        'property' => 'related',
                        'ref' => '#/components/schemas/link',
                    ]),
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
                                'description' => 'A string containing the link\'s URL.',
                                'type' => 'string',
                                'format' => 'uri-reference',
                            ],
                            'meta' => ['$ref' => '#/components/schemas/meta'],
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
                            'links' => ['$ref' => '#/components/schemas/relationshipLinks'],
                            'data' => [
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
                'allOf' => [
                    new Schema([
                        'description' => 'References to other resource objects in a to-one ("relationship"). Relationships can be specified by including a member in a resource\'s links object.',
                    ]),
                    new Schema([
                        'ref' => '#/components/schemas/linkage',
                    ]),
                ],
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
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'additionalProperties' => false,
            ]),
            'pagination' => new Schema([
                'schema' => 'pagination',
                'type' => 'object',
                'properties' => [
                    'first' => [
                        'description' => 'The first page of data',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'last' => [
                        'description' => 'The last page of data',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'prev' => [
                        'description' => 'The previous page of data',
                        'type' => 'string',
                        'format' => 'uri-reference',
                    ],
                    'next' => [
                        'description' => 'The next page of data',
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
                    'version' => ['type' => 'string'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'additionalProperties' => false,
            ]),
            'error' => new Schema([
                'schema' => 'error',
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'A unique identifier for this particular occurrence of the problem.'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'status' => ['type' => 'string', 'description' => 'The HTTP status code applicable to this problem, expressed as a string value.'],
                    'code' => ['type' => 'string', 'description' => 'An application-specific error code, expressed as a string value.'],
                    'title' => ['type' => 'string', 'description' => 'A short, human-readable summary of the problem. It **SHOULD NOT** change from occurrence to occurrence of the problem, except for purposes of localization.'],
                    'detail' => ['type' => 'string', 'description' => 'A human-readable explanation specific to this occurrence of the problem.'],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'pointer' => ['type' => 'string', 'description' => 'A JSON Pointer [RFC6901] to the associated entity in the request document [e.g. "/data" for a primary data object, or "/data/attributes/title" for a specific attribute].'],
                            'parameter' => ['type' => 'string', 'description' => 'A string indicating which query parameter caused the error.'],
                        ],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'additionalProperties' => false,
            ]),
        ];

        return $defaults;
    }

    /**
     * @return SecurityScheme[]
     */
    private function createSecurityScheme(string $api): array
    {
        if (self::API[$api]['apiKey']) {
            return [
                'Sales Channel Access Key' => new SecurityScheme([
                    'securityScheme' => 'ApiKey',
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => PlatformRequest::HEADER_ACCESS_KEY,
                    'description' => 'Identifies the sales channel you want to access the API through',
                ]),
                'User Context Token' => new SecurityScheme([
                    'securityScheme' => 'ContextToken',
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => PlatformRequest::HEADER_CONTEXT_TOKEN,
                    'description' => 'Identifies an anonymous or identified user session',
                ]),
            ];
        }

        $url = (string) EnvironmentHelper::getVariable('APP_URL', '');

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
            Response::HTTP_FORBIDDEN => $this->createErrorResponse(Response::HTTP_FORBIDDEN, 'Forbidden', 'This operation is restricted to logged in users.'),
            Response::HTTP_UNAUTHORIZED => $this->createErrorResponse(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Authorization information is missing or invalid.'),
            Response::HTTP_BAD_REQUEST => $this->createErrorResponse(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Bad parameters for this endpoint. See documentation for the correct ones.'),
            Response::HTTP_NO_CONTENT => new OpenApiResponse(['description' => 'No Content', 'response' => Response::HTTP_NO_CONTENT]),
        ];
    }

    private function createErrorResponse(int $statusCode, string $title, string $description): OpenApiResponse
    {
        $schema = new Schema([
            'ref' => '#/components/schemas/failure',
        ]);

        $example = [
            'errors' => [
                [
                    'status' => (string) $statusCode,
                    'title' => $title,
                    'description' => $description,
                ],
            ],
        ];

        return new OpenApiResponse([
            'response' => $statusCode,
            'description' => $title,
            'content' => [
                new MediaType([
                    'mediaType' => 'application/vnd.api+json',
                    'schema' => $schema,
                    'example' => $example,
                ]),
                new MediaType([
                    'mediaType' => 'application/json',
                    'schema' => $schema,
                    'example' => $example,
                ]),
            ],
        ]);
    }
}
