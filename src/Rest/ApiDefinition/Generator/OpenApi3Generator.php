<?php declare(strict_types=1);

namespace Shopware\Rest\ApiDefinition\Generator;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\LongTextWithHtmlField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Rest\ApiDefinition\ApiDefinitionGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;

class OpenApi3Generator implements ApiDefinitionGeneratorInterface
{
    public const FORMAT = 'openapi-3';

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    public function __construct(DefinitionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function supports(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function generate(): array
    {
        $openapi = [
            'openapi' => '3.0.0',
            'servers' => [
                ['url' => 'http://shopware.next/api'],
            ],
            'info' => [
                'title' => 'Shopware API',
                'version' => '1.0.0',
            ],
            'security' => [
                ['bearerAuth' => new \StdClass()],
            ],
            'tags' => [
                ['name' => 'Auth', 'description' => 'Endpoint for consumer authentication.'],
            ],
            'paths' => [
                '/auth' => $this->getAuthPath(),
            ],
            'components' => [
                'schemas' => [
                    'auth' => $this->getAuthSchema(),
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'responses' => [
                    Response::HTTP_NOT_FOUND => $this->createErrorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'Resource with given parameter was not found.'),
                    Response::HTTP_UNAUTHORIZED => $this->createErrorResponse(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Authorization information is missing or invalid.'),
                    Response::HTTP_BAD_REQUEST => $this->createErrorResponse(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Bad parameters for this endpoint. See documentation for the correct ones.'),
                    Response::HTTP_NO_CONTENT => ['description' => 'The resource was deleted successfully.'],
                ],
            ],
        ];

        $elements = $this->registry->getElements();

        ksort($elements);

        foreach ($elements as $definition) {
            if (preg_match('/_translation$/', $definition::getEntityName())) {
                continue;
            }

            if (preg_match('/^audit_log/', $definition::getEntityName())) {
                continue;
            }

            /* @var EntityDefinition $definition */
            try {
                $definition::getRepositoryClass();
            } catch (\Exception $e) {
                //mapping tables has no repository, skip them
                continue;
            }

            $openapi['components']['schemas'] = array_merge(
                $openapi['components']['schemas'],
                $this->getSchemaByDefinition($definition),
                $this->getSchemaByDefinition($definition, true)
            );

            $openapi = $this->addListPathActions($openapi, $definition);
            $openapi = $this->addDetailPathActions($openapi, $definition);

            $humanReadableName = $this->convertToHumanReadable($definition::getEntityName());

            $openapi['tags'][] = ['name' => $humanReadableName, 'description' => 'The endpoint for operations on ' . $humanReadableName];
        }

        return $openapi;
    }

    /**
     * @return array
     */
    public function getSchema(): array
    {
        $schemaDefinitions = [];
        $elements = $this->registry->getElements();

        ksort($elements);

        foreach ($elements as $definition) {
            if (preg_match('/_translation$/', $definition::getEntityName())) {
                continue;
            }

            /* @var string|EntityDefinition $definition */
            try {
                $definition::getRepositoryClass();
            } catch (\Exception $e) {
                //mapping tables has no repository, skip them
                continue;
            }

            $schema = $this->getSchemaByDefinition($definition, true);
            $schema = array_shift($schema);
            $schema = $schema['allOf'][1]['properties'];

            $relationships = [];
            if (array_key_exists('relationships', $schema)) {
                foreach ($schema['relationships']['properties'] as $propertyName => $relationship) {
                    $relationshipData = $relationship['allOf'][1]['properties']['data']['allOf'][1];
                    $type = $relationshipData['type'];

                    if ($type === 'object') {
                        $entity = $relationshipData['properties']['type']['example'];
                    } elseif ($type === 'array') {
                        $entity = $relationshipData['items']['allOf'][1]['properties']['type']['example'];
                    } else {
                        throw new \RuntimeException('Invalid schema detected. Aborting');
                    }

                    $relationships[$propertyName] = [
                        'type' => $type,
                        'entity' => $entity,
                    ];
                }
            }

            $properties = array_merge(
                [
                    'id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                    ],
                ],
                $schema['attributes']['allOf'][1]['properties'],
                $relationships
            );

            $schemaDefinitions[$definition::getEntityName()] = [
                'name' => $definition::getEntityName(),
                'required' => $schema['attributes']['allOf'][1]['required'],
                'properties' => $properties,
            ];
        }

        return $schemaDefinitions;
    }

    private function convertToHumanReadable(string $name): string
    {
        $name = explode('_', $name);
        $name = array_map('ucfirst', $name);
        $name = implode(' ', $name);

        return $name;
    }

    private function getType(Field $field): string
    {
        switch (true) {
            case $field instanceof OneToManyAssociationField:
            case $field instanceof ManyToManyAssociationField:
                return 'array';
            case $field instanceof FloatField:
                return 'number';
            case $field instanceof IntField:
                return 'integer';
            case $field instanceof BoolField:
                return 'boolean';
            case $field instanceof DateField:
            case $field instanceof IdField:
            case $field instanceof FkField:
            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
            case $field instanceof TranslatedField:
            case $field instanceof StringField:
            default:
                return 'string';
        }
    }

    private function getPropertyByField(Field $field)
    {
        $property = [
            'type' => $this->getType($field),
        ];

        if ($field instanceof DateField) {
            $property['format'] = 'date-time';
        }

        if ($field instanceof FloatField) {
            $property['format'] = 'float';
        }

        switch ($property['type']) {
            case 'int':
                $property['format'] = 'int64';
                break;
        }

        return $property;
    }

    /**
     * @param string|EntityDefinition $definition
     * @param bool                    $detailSchema
     *
     * @return array
     */
    private function getSchemaByDefinition(string $definition, bool $detailSchema = false): array
    {
        $attributes = [];
        $requiredAttributes = [];
        $relationships = [];

        $uuid = Uuid::uuid4()->toString();
        $schemaName = $definition::getEntityName() . '_' . ($detailSchema ? 'detail' : 'basic');
        $detailPath = $this->getResourceUri($definition) . '/' . $uuid;

        /** @var Field $field */
        foreach ($definition::getFields() as $field) {
            if ($field->getPropertyName() === 'translations' || $field->getPropertyName() === 'id') {
                continue;
            }

            if ($detailSchema === false && $field instanceof AssociationInterface && $field->loadInBasic() === false) {
                continue;
            }

            if ($field->is(Required::class)) {
                $requiredAttributes[] = $field->getPropertyName();
            }

            if ($field instanceof ManyToOneAssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToOneLinkage($field, $detailPath);
                continue;
            }

            if ($field instanceof AssociationInterface) {
                $relationships[$field->getPropertyName()] = $this->createToManyLinkage($field, $detailPath);
                continue;
            }

            $attributes[$field->getPropertyName()] = $this->getPropertyByField($field);
        }

        $schema = [
            $schemaName => [
                'allOf' => [
                    ['$ref' => 'http://jsonapi.org/schema#/definitions/resource'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['example' => $definition::getEntityName()],
                            'id' => ['example' => $uuid],
                            'attributes' => [
                                'allOf' => [
                                    ['$ref' => 'http://jsonapi.org/schema#/definitions/attributes'],
                                    [
                                        'type' => 'object',
                                        'required' => $requiredAttributes,
                                        'properties' => $attributes,
                                    ],
                                ],
                            ],
                            'links' => [
                                'allOf' => [
                                    ['$ref' => 'http://jsonapi.org/schema#/definitions/links'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'self' => [
                                                'type' => 'string',
                                                'example' => $detailPath,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (count($relationships)) {
            $schema[$schemaName]['allOf'][1]['properties']['relationships']['properties'] = $relationships;
        }

        return $schema;
    }

    /**
     * @param array                   $openapi
     * @param string|EntityDefinition $definition
     *
     * @return array
     */
    private function addListPathActions(array $openapi, string $definition): array
    {
        $humanReadableName = $this->convertToHumanReadable($definition::getEntityName());
        $path = $this->getResourceUri($definition);

        $schemaName = $definition::getEntityName() . '_basic';

        $openapi['paths'][$path] = [
            'get' => [
                'summary' => 'List with basic information of ' . $humanReadableName . ' resources',
                'tags' => [$humanReadableName],
                'parameters' => $this->getDefaultListingParameter(),
                'responses' => [
                    Response::HTTP_OK => [
                        'description' => 'List of ' . $humanReadableName . ' resources.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'allOf' => [
                                        ['$ref' => 'http://jsonapi.org/schema#/definitions/success'],
                                        [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    'allOf' => [
                                                        ['$ref' => 'http://jsonapi.org/schema#/definitions/data'],
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
                                                        ['$ref' => 'http://jsonapi.org/schema#/definitions/pagination'],
                                                        [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'first' => ['example' => $path . '?page[limit]=25'],
                                                                'last' => ['example' => $path . '?page[limit]=25&page[offset]=250'],
                                                                'next' => ['example' => $path . '?page[limit]=25&page[offset]=75'],
                                                                'prev' => ['example' => $path . '?page[limit]=25&page[offset]=25'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
            'post' => [
                'summary' => 'Create a new ' . $humanReadableName . ' resources',
                'tags' => [$humanReadableName],
                'requestBody' => [
                    'description' => 'Create a new ' . $humanReadableName . ' resources. All required fields must be provided in order to create a new resource successfully.',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $definition::getEntityName() . '_detail',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    Response::HTTP_OK => $this->getDetailResponse($schemaName),
                    Response::HTTP_BAD_REQUEST => $this->getDetailResponse($schemaName),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
        ];

        return $openapi;
    }

    /**
     * @param array                   $openapi
     * @param string|EntityDefinition $definition
     *
     * @return array
     */
    private function addDetailPathActions(array $openapi, string $definition): array
    {
        $humanReadableName = $this->convertToHumanReadable($definition::getEntityName());

        $schemaName = $definition::getEntityName() . '_detail';
        $path = $this->getResourceUri($definition) . '/{id}';

        $openapi['paths'][$path] = [
            'get' => [
                'summary' => 'Detailed information about a ' . $humanReadableName . ' resource',
                'tags' => [$humanReadableName],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'schema' => ['type' => 'string'],
                        'description' => 'Identifier for the ' . $definition::getEntityName(),
                        'required' => true,
                    ],
                ],
                'responses' => [
                    Response::HTTP_OK => $this->getDetailResponse($schemaName),
                    Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
            'patch' => [
                'summary' => 'Partially update information about a ' . $humanReadableName . ' resource',
                'tags' => [$humanReadableName],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'schema' => ['type' => 'string'],
                        'description' => 'Identifier for the ' . $definition::getEntityName(),
                        'required' => true,
                    ],
                ],
                'requestBody' => [
                    'description' => 'Partially update information about a ' . $humanReadableName . ' resource.',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $definition::getEntityName() . '_detail',
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
            ],
            'delete' => [
                'summary' => 'Delete a ' . $humanReadableName . ' resource',
                'tags' => [$humanReadableName],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'schema' => ['type' => 'string'],
                        'description' => 'Identifier for the ' . $definition::getEntityName(),
                        'required' => true,
                    ],
                ],
                'responses' => [
                    Response::HTTP_NO_CONTENT => $this->getResponseRef((string) Response::HTTP_NO_CONTENT),
                    Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
        ];

        return $openapi;
    }

    private function createToOneLinkage(ManyToOneAssociationField $field, string $basePath): array
    {
        return [
            'allOf' => [
                ['$ref' => 'http://jsonapi.org/schema#/definitions/relationships'],
                [
                    'type' => 'object',
                    'properties' => [
                        'links' => [
                            'allOf' => [
                                ['$ref' => 'http://jsonapi.org/schema#/definitions/relationshipLinks'],
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'related' => [
                                            'type' => 'string',
                                            'format' => 'uri-reference',
                                            'example' => $basePath . '/' . $field->getPropertyName(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'data' => [
                            'allOf' => [
                                ['$ref' => 'http://jsonapi.org/schema#/definitions/linkage'],
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'type' => [
                                            'type' => 'string',
                                            'example' => $field->getReferenceClass()::getEntityName(),
                                        ],
                                        'id' => [
                                            'type' => 'string',
                                            'example' => Uuid::uuid4()->toString(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param ManyToManyAssociationField|OneToManyAssociationField|AssociationInterface $field
     * @param string                                                                    $basePath
     *
     * @return array
     */
    private function createToManyLinkage(AssociationInterface $field, string $basePath)
    {
        $associationEntityName = $field->getReferenceClass()::getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $associationEntityName = $field->getReferenceDefinition()::getEntityName();
        }

        return [
            'allOf' => [
                ['$ref' => 'http://jsonapi.org/schema#/definitions/relationships'],
                [
                    'type' => 'object',
                    'properties' => [
                        'links' => [
                            'allOf' => [
                                ['$ref' => 'http://jsonapi.org/schema#/definitions/relationshipLinks'],
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'related' => [
                                            'type' => 'string',
                                            'format' => 'uri-reference',
                                            'example' => $basePath . '/' . $field->getPropertyName(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'data' => [
                            'allOf' => [
                                ['$ref' => 'http://jsonapi.org/schema#/definitions/relationshipToMany'],
                                [
                                    'type' => 'array',
                                    'items' => [
                                        'allOf' => [
                                            ['$ref' => 'http://jsonapi.org/schema#/definitions/linkage'],
                                            [
                                                'type' => 'object',
                                                'properties' => [
                                                    'type' => [
                                                        'type' => 'string',
                                                        'example' => $associationEntityName,
                                                    ],
                                                    'id' => [
                                                        'type' => 'string',
                                                        'example' => Uuid::uuid4()->toString(),
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getDefaultListingParameter(): array
    {
        return [
            [
                'name' => 'page[limit]',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'Max amount of resources to be returned',
            ],
            [
                'name' => 'page[offset]',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'Offset of the searched results',
            ],
            [
                'name' => 'query',
                'in' => 'query',
                'schema' => [
                    'type' => 'string',
                ],
                'description' => 'Encoded SwagQL in JSON',
            ],
        ];
    }

    private function getAuthSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'example' => 'admin',
                    'description' => 'Username for authentication.',
                ],
                'password' => [
                    'type' => 'string',
                    'example' => 'shopware',
                    'description' => 'Password for authentication.',
                ],
            ],
        ];
    }

    /**
     * @param string|EntityDefinition $definition
     * @param string                  $rootPath
     *
     * @return string
     */
    private function getResourceUri(string $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition::getEntityName());
    }

    private function createErrorResponse(int $statusCode, string $title, string $description): array
    {
        return [
            'description' => $title,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => 'http://jsonapi.org/schema#/definitions/failure'],
                        ],
                    ],
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
        ];
    }

    /**
     * @param $schemaName
     *
     * @return array
     */
    private function getDetailResponse($schemaName): array
    {
        return [
            'description' => 'Detail of ' . $schemaName,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => 'http://jsonapi.org/schema#/definitions/success'],
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
            ],
        ];
    }

    private function getResponseRef(string $responseName): array
    {
        return [
            '$ref' => '#/components/responses/' . $responseName,
        ];
    }

    private function getAuthPath(): array
    {
        return [
            'post' => [
                'tags' => ['Auth'],
                'security' => [],
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/auth',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    Response::HTTP_OK => [
                        'description' => 'Response with authentication token for further requests',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'description' => 'The token that should be used for future requests.',
                                            'example' => 'eyJhbGciOiJSUzI1NiJ9.eyJyb2xlcyI6WyJJU19BVVRIRU5USUNBVEVEX0ZVTExZIiwiUk9MRV9BRE1JTiJdLCJ1c2VybmFtZSI6ImFkbWluIiwiaWF0IjoxNTE0OTAzODA3LCJleHAiOjE1MTQ5MDc0MDd9.b28BFrxJ_g6KvuGwbtI4LdhxBQOs3SEw_gIUD-zD6rzFyekACFwDupCSLX-emFDJb9UztJyugIGpEfdGkwtwUxf_gpHyV85FsfCMFlb00fUpdBXD8pP2qu9oBjVgtcPxLeKolY_OXdCcHR40yu-dnxb853uLnJhOIlPIJYMxayf7XLenYtOtQnJ9W7RlTOBLqFxa_qQqGV7wlhq8JUy9gyfbvxIPFE4hH53wQ4jagO6kUlOFYXKLn9lQrrWOhEMq7YqYImbRuWGu6i5a2sa1-k5BxqlzLR2B5WPteDTa7tDZqZsK1CSma5hz0zbusggg8iycQ-nvecAP9jQ6Z83ZEg',
                                        ],
                                        'expiry' => [
                                            'type' => 'integer',
                                            'description' => 'Datetime as unix time when the token expires',
                                            'example' => 1514907407,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_BAD_REQUEST => $this->getResponseRef((string) Response::HTTP_BAD_REQUEST),
                ],
            ],
        ];
    }
}
