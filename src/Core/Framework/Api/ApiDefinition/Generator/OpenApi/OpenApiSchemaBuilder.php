<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Response as OpenApiResponse;
use OpenApi\Annotations\Schema;
use OpenApi\Annotations\SecurityScheme;
use OpenApi\Annotations\Server;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the document skeleton every generated OpenAPI specification starts from: info, servers, security and the
 * default components.
 *
 * @phpstan-type OpenApiDocument array{openapi: string, info: array<string, mixed>, servers: list<array{url: string}>, paths: array<string, array<string, mixed>>, components: array<string, array<array-key, mixed>>, security: list<array<string, list<string>>>, tags?: list<array<string, mixed>>}
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class OpenApiSchemaBuilder
{
    final public const OPENAPI_VERSION = '3.2.0';

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

    /**
     * The document skeleton for the given API. The generators add paths, component schemas and tags to it.
     *
     * @internal
     *
     * @return OpenApiDocument
     */
    public function createSpec(string $api): array
    {
        $components = [];

        if ($api !== DefinitionService::STORE_API) {
            $components['schemas'] = $this->getDefaultSchemas();
        }

        $components['responses'] = $this->createDefaultResponses($api);
        $components['securitySchemes'] = $this->createSecurityScheme($api);

        return [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->createInfo($api, $this->version),
            'servers' => $this->createServers($api),
            'paths' => [],
            'components' => $components,
            'security' => [$this->createSecurity($api)],
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, the class becomes internal
     */
    public function enrich(OpenApi $openApi, string $api): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $spec = $this->createSpec($api);

        $openApi->merge(array_map(static fn (array $server): Server => new Server($server), $spec['servers']));
        $openApi->info = new Info($spec['info']);

        $security = $openApi->security;
        $openApi->security = [array_merge(\is_array($security) ? $security : [], $spec['security'][0])];

        if (!$openApi->components instanceof Components) {
            $openApi->components = new Components([]);
        }

        foreach ($spec['components']['schemas'] ?? [] as $name => $schema) {
            $openApi->components->merge([new Schema(['schema' => $name] + $schema)]);
        }

        foreach ($spec['components']['securitySchemes'] as $name => $securityScheme) {
            $openApi->components->merge([new SecurityScheme(['securityScheme' => $name] + $securityScheme)]);
        }

        foreach ($spec['components']['responses'] as $statusCode => $response) {
            $openApi->components->merge([new OpenApiResponse(['response' => $statusCode] + $response)]);
        }
    }

    /**
     * @return list<array{url: string}>
     */
    private function createServers(string $api): array
    {
        $url = (string) EnvironmentHelper::getVariable('APP_URL', '');

        if ($this->shouldUseRelativeServerUrl($url)) {
            return [
                ['url' => self::API[$api]['url']],
            ];
        }

        return [
            ['url' => rtrim($url, '/') . self::API[$api]['url']],
        ];
    }

    private function shouldUseRelativeServerUrl(string $url): bool
    {
        if (EnvironmentHelper::getVariable('APP_ENV', 'prod') !== 'prod') {
            return false;
        }

        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && \in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return array{title: string, description: string, license: array{name: string, url: string}, version: string}
     */
    private function createInfo(string $api, string $version): array
    {
        return [
            'title' => 'Shopware ' . self::API[$api]['name'],
            'description' => <<<'EOF'
This endpoint reference contains an overview of all endpoints comprising the Shopware Admin API.

For a better overview, all CRUD-endpoints are hidden by default. If you want to show also CRUD-endpoints
add the query parameter `type=jsonapi`.
EOF,
            'license' => [
                'name' => 'MIT',
                'url' => 'https://github.com/shopware/shopware/blob/trunk/LICENSE',
            ],
            'version' => $version,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function createSecurity(string $api): array
    {
        if (self::API[$api]['apiKey']) {
            return ['ApiKey' => []];
        }

        return ['oAuth' => ['write']];
    }

    /**
     * The JSON:API document building blocks of the Admin API, keyed by schema name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultSchemas(): array
    {
        return [
            'success' => [
                'required' => ['data'],
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
                'type' => 'object',
                'additionalProperties' => false,
            ],
            'failure' => [
                'required' => ['errors'],
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'errors' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/error'],
                        'uniqueItems' => true,
                    ],
                ],
                'type' => 'object',
                'additionalProperties' => false,
            ],
            'info' => [
                'required' => ['meta'],
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'jsonapi' => ['$ref' => '#/components/schemas/jsonapi'],
                ],
                'type' => 'object',
            ],
            'meta' => [
                'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'data' => [
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
            ],
            'resource' => [
                'description' => '"Resource objects" appear in a JSON API document to represent resources.',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                    'attributes' => ['$ref' => '#/components/schemas/attributes'],
                    'relationships' => ['$ref' => '#/components/schemas/relationships'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'type' => 'object',
            ],
            'relationshipLinks' => [
                'description' => 'A resource object **MAY** contain references to other resource objects ("relationships"). Relationships may be to-one or to-many. Relationships can be specified by including a member in a resource\'s links object.',
                'properties' => [
                    'self' => [
                        'allOf' => [
                            [
                                'description' => 'A `self` member, whose value is a URL for the relationship itself (a "relationship URL"). This URL allows the client to directly manipulate the relationship. For example, it would allow a client to remove an `author` from an `article` without deleting the people resource itself.',
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                ],
                            ],
                            ['$ref' => '#/components/schemas/link'],
                        ],
                    ],
                    'related' => ['$ref' => '#/components/schemas/link'],
                ],
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'links' => [
                'type' => 'object',
                'additionalProperties' => [
                    '$ref' => '#/components/schemas/link',
                ],
            ],
            'link' => [
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
            ],
            'attributes' => [
                'description' => 'Members of the attributes object ("attributes") represent information about the resource object in which it\'s defined.',
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'relationships' => [
                'description' => 'Members of the relationships object ("relationships") represent references from the resource object in which it\'s defined to other resource objects.',
                'type' => 'object',
                'additionalProperties' => [
                    '$ref' => '#/components/schemas/relationship',
                ],
            ],
            'relationship' => [
                'description' => 'A relationship object describes links, resource linkage, or meta-information for a related resource.',
                'minProperties' => 1,
                'properties' => [
                    'links' => ['$ref' => '#/components/schemas/relationshipLinks'],
                    'data' => [
                        'description' => 'Member, whose value represents "resource linkage".',
                        'oneOf' => [
                            ['$ref' => '#/components/schemas/relationshipToOne'],
                            ['$ref' => '#/components/schemas/relationshipToMany'],
                        ],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'type' => 'object',
                'additionalProperties' => false,
            ],
            'relationshipToOne' => [
                'allOf' => [
                    [
                        'description' => 'References to other resource objects in a to-one ("relationship"). Relationships can be specified by including a member in a resource\'s links object.',
                    ],
                    ['$ref' => '#/components/schemas/linkage'],
                ],
            ],
            'relationshipToMany' => [
                'description' => 'An array of objects each containing \"type\" and \"id\" members for to-many relationships.',
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/linkage',
                ],
                'uniqueItems' => true,
            ],
            'linkage' => [
                'description' => 'The "type" and "id" to non-empty members.',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'type' => 'object',
                'additionalProperties' => false,
            ],
            'pagination' => [
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
                'type' => 'object',
            ],
            'jsonapi' => [
                'description' => 'An object describing the server\'s implementation',
                'properties' => [
                    'version' => ['type' => 'string'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'type' => 'object',
                'additionalProperties' => false,
            ],
            'error' => [
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'A unique identifier for this particular occurrence of the problem.'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'status' => ['type' => 'string', 'description' => 'The HTTP status code applicable to this problem, expressed as a string value.'],
                    'code' => ['type' => 'string', 'description' => 'An application-specific error code, expressed as a string value.'],
                    'title' => ['type' => 'string', 'description' => 'A short, human-readable summary of the problem. It **SHOULD NOT** change from occurrence to occurrence of the problem, except for purposes of localization.'],
                    'detail' => ['type' => 'string', 'description' => 'A human-readable explanation specific to this occurrence of the problem.'],
                    'description' => ['type' => 'string', 'description' => 'A human-readable description of the problem.'],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'pointer' => ['type' => 'string', 'description' => 'A JSON Pointer [RFC6901] to the associated entity in the request document [e.g. "/data" for a primary data object, or "/data/attributes/title" for a specific attribute].'],
                            'parameter' => ['type' => 'string', 'description' => 'A string indicating which query parameter caused the error.'],
                        ],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'type' => 'object',
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createSecurityScheme(string $api): array
    {
        if (self::API[$api]['apiKey']) {
            return [
                'ApiKey' => [
                    'type' => 'apiKey',
                    'description' => 'Identifies the sales channel you want to access the API through',
                    'name' => PlatformRequest::HEADER_ACCESS_KEY,
                    'in' => 'header',
                ],
                'ContextToken' => [
                    'type' => 'apiKey',
                    'description' => 'Identifies an anonymous or identified user session',
                    'name' => PlatformRequest::HEADER_CONTEXT_TOKEN,
                    'in' => 'header',
                ],
            ];
        }

        $url = (string) EnvironmentHelper::getVariable('APP_URL', '');

        return [
            'oAuth' => [
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
                    'authorizationCode' => [
                        'authorizationUrl' => $url . '/api/oauth/authorize',
                        'tokenUrl' => $url . '/api/oauth/token',
                        'scopes' => [
                            'write' => 'Full write access',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createDefaultResponses(string $api): array
    {
        $responses = [
            Response::HTTP_NOT_FOUND => $this->createErrorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'Resource with given parameter was not found.'),
            Response::HTTP_FORBIDDEN => $this->createErrorResponse(Response::HTTP_FORBIDDEN, 'Forbidden', 'This operation is restricted to logged in users.'),
            Response::HTTP_UNAUTHORIZED => $this->createErrorResponse(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Authorization information is missing or invalid.'),
            Response::HTTP_BAD_REQUEST => $this->createErrorResponse(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Bad parameters for this endpoint. See documentation for the correct ones.'),
            Response::HTTP_TOO_MANY_REQUESTS => $this->createErrorResponse(Response::HTTP_TOO_MANY_REQUESTS, 'Too Many Requests', 'Rate limit exceeded. Please wait before retrying.'),
        ];

        if ($api !== DefinitionService::STORE_API) {
            $responses[Response::HTTP_NO_CONTENT] = ['description' => 'No Content'];
        }

        return $responses;
    }

    /**
     * @return array{description: string, content: array<string, array{schema: array{'$ref': string}, example: array<string, mixed>}>}
     */
    private function createErrorResponse(int $statusCode, string $title, string $description): array
    {
        $schema = [
            '$ref' => '#/components/schemas/failure',
        ];

        $example = [
            'errors' => [
                [
                    'status' => (string) $statusCode,
                    'title' => $title,
                    'description' => $description,
                ],
            ],
        ];

        return [
            'description' => $title,
            'content' => [
                'application/vnd.api+json' => [
                    'schema' => $schema,
                    'example' => $example,
                ],
                'application/json' => [
                    'schema' => $schema,
                    'example' => $example,
                ],
            ],
        ];
    }
}
