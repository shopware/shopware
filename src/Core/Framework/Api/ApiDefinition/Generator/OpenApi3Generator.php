<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\HttpFoundation\Response;

class OpenApi3Generator implements ApiDefinitionGeneratorInterface
{
    public const FORMAT = 'openapi-3';

    public function supports(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function generate(array $definitions): array
    {
        $url = getenv('APP_URL');

        $forSalesChannel = $this->containsSalesChannelDefinition($definitions);

        $openapi = [
            'openapi' => '3.0.0',
            'servers' => [
                ['url' => rtrim($url, '/') . ($forSalesChannel ? '/sales-channel-api/v1' : '/api/v1')],
            ],
            'info' => [
                'title' => 'Shopware ' . ($forSalesChannel ? 'Sales-Channel' : 'Management') . ' API',
                'version' => '1.0.0',
            ],
            'security' => $this->createSecurity($forSalesChannel),
            'tags' => [],
            'paths' => [],
            'components' => [
                'schemas' => $this->getDefaultSchemas(),
                'securitySchemes' => $this->createSecurityScheme($forSalesChannel),
                'responses' => [
                    Response::HTTP_NOT_FOUND => $this->createErrorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'Resource with given parameter was not found.'),
                    Response::HTTP_UNAUTHORIZED => $this->createErrorResponse(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Authorization information is missing or invalid.'),
                    Response::HTTP_BAD_REQUEST => $this->createErrorResponse(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Bad parameters for this endpoint. See documentation for the correct ones.'),
                    Response::HTTP_NO_CONTENT => ['description' => 'The resource was deleted successfully.'],
                ],
            ],
        ];

        ksort($definitions);

        foreach ($definitions as $definition) {
            $onlyReference = false;
            if (preg_match('/_translation$/', $definition->getEntityName())) {
                continue;
            }

            if (mb_strpos($definition->getEntityName(), 'version') === 0) {
                continue;
            }

            try {
                $class = new \ReflectionClass($definition);
                if ($class->isSubclassOf(MappingEntityDefinition::class)) {
                    $onlyReference = true;
                }
            } catch (\ReflectionException $e) {
                continue;
            }

            if ($forSalesChannel && !is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
                $onlyReference = true;
            }

            $schema = $this->getSchemaByDefinition($definition, $forSalesChannel);

            if ($onlyReference) {
                // if the definition is only used for references we just need the flat schema
                unset($schema[$definition->getEntityName()]);
            }

            $openapi['components']['schemas'] = array_merge(
                $openapi['components']['schemas'],
                $schema
            );

            if ($onlyReference) {
                continue;
            }

            $openapi = $this->addPathActions($openapi, $definition);

            $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

            $openapi['tags'][] = ['name' => $humanReadableName, 'description' => 'The endpoint for operations on ' . $humanReadableName];
        }

        return $openapi;
    }

    public function getSchema(array $definitions): array
    {
        $schemaDefinitions = [];

        $forSalesChannel = $this->containsSalesChannelDefinition($definitions);

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (preg_match('/_translation$/', $definition->getEntityName())) {
                continue;
            }

            try {
                $definition->getEntityName();
            } catch (\Exception $e) {
                //mapping tables has no repository, skip them
                continue;
            }

            $schema = $this->getSchemaByDefinition($definition, $forSalesChannel);
            $schema = array_shift($schema);
            $schema = $schema['allOf'][1]['properties'];

            $relationships = [];
            if (array_key_exists('relationships', $schema)) {
                foreach ($schema['relationships']['properties'] as $propertyName => $extension) {
                    $relationshipData = $extension['properties']['data'];
                    $type = $relationshipData['type'];

                    if ($type === 'object') {
                        $entity = $relationshipData['properties']['type']['example'];
                    } elseif ($type === 'array') {
                        $entity = $relationshipData['items']['properties']['type']['example'];
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
                $schema['attributes']['properties'],
                $relationships
            );

            if (array_key_exists('extensions', $properties)) {
                $extensions = [];

                foreach ($properties['extensions']['properties'] as $propertyName => $extension) {
                    $field = $definition->getFields()->get($propertyName);

                    if (!$field instanceof AssociationField) {
                        $extensions[$propertyName] = $extension;
                        continue;
                    }

                    $data = $extension['properties']['data'];
                    $type = $data['type'];

                    if ($type === 'object') {
                        $entity = $data['properties']['type']['example'];
                    } elseif ($type === 'array') {
                        $entity = $data['items']['properties']['type']['example'];
                    } else {
                        throw new \RuntimeException('Invalid schema detected. Aborting');
                    }

                    $extensions[$propertyName] = ['type' => $type, 'entity' => $entity];
                }

                $properties['extensions']['properties'] = $extensions;
            }

            $entityName = $definition->getEntityName();
            $schemaDefinitions[$entityName] = [
                'name' => $entityName,
                'required' => $schema['attributes']['required'],
                'translatable' => $definition->getFields()->filterInstance(TranslatedField::class)->getKeys(),
                'properties' => $properties,
            ];
        }

        return $schemaDefinitions;
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

    private function resolveJsonField(JsonField $jsonField): array
    {
        if ($jsonField instanceof ListField) {
            $definition = [
                'type' => 'array',
                'items' => $jsonField->getFieldType() ? $this->getPropertyByField($jsonField->getFieldType()) : [],
            ];
        } else {
            $definition = [
                'type' => 'object',
            ];
        }

        $required = [];

        foreach ($jsonField->getPropertyMapping() as $field) {
            if ($field instanceof JsonField) {
                $definition['properties'][$field->getPropertyName()] = $this->resolveJsonField($field);
                continue;
            }

            if ($field->is(Required::class)) {
                $required[] = $field->getPropertyName();
            }

            $definition['properties'][$field->getPropertyName()] = $this->getPropertyByField(\get_class($field));
        }

        if (\count($required)) {
            $definition['required'] = $required;
        }

        /** @var WriteProtected|null $writeProtection */
        $writeProtection = $jsonField->getFlag(WriteProtected::class);
        if ($writeProtection && !$writeProtection->isAllowed(Context::USER_SCOPE)) {
            $definition['readOnly'] = true;
        }

        return $definition;
    }

    private function getType(string $fieldClass): string
    {
        if (\is_a($fieldClass, FloatField::class, true)) {
            return 'number';
        }
        if (\is_a($fieldClass, IntField::class, true)) {
            return 'integer';
        }
        if (\is_a($fieldClass, BoolField::class, true)) {
            return 'boolean';
        }
        if (\is_a($fieldClass, ListField::class, true)) {
            return 'array';
        }
        if (\is_a($fieldClass, JsonField::class, true)) {
            return 'object';
        }

        return 'string';
    }

    private function getPropertyByField(string $fieldClass): array
    {
        $property = [
            'type' => $this->getType($fieldClass),
        ];

        if (\is_a($fieldClass, DateTimeField::class, true)) {
            $property['format'] = 'date-time';
        }
        if (\is_a($fieldClass, FloatField::class, true)) {
            $property['format'] = 'float';
        }
        if (\is_a($fieldClass, IntField::class, true)) {
            $property['format'] = 'int64';
        }
        if (\is_a($fieldClass, IdField::class, true) || \is_a($fieldClass, FkField::class, true)) {
            $property['type'] = 'string';
            $property['format'] = 'uuid';
        }

        return $property;
    }

    private function getSchemaByDefinition(EntityDefinition $definition, bool $forSalesChannel): array
    {
        $attributes = [];
        $requiredAttributes = [];
        $relationships = [];

        $uuid = Uuid::randomHex();
        $schemaName = $definition->getEntityName();
        $detailPath = $this->getResourceUri($definition) . '/' . $uuid;

        $extensions = [];

        $apiSource = $forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class;
        /** @var Field $field */
        foreach ($definition->getFields() as $field) {
            if ($field->getPropertyName() === 'translations'
                || $field->getPropertyName() === 'id'
                || preg_match('#translations$#i', $field->getPropertyName())) {
                continue;
            }

            /** @var ReadProtected|null $readProtected */
            $readProtected = $field->getFlag(ReadProtected::class);
            if ($readProtected && !$readProtected->isSourceAllowed($apiSource)) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $extensions[] = $field;
                continue;
            }

            if ($field->is(Required::class) && !$field instanceof VersionField && !$field instanceof ReferenceVersionField) {
                $requiredAttributes[] = $field->getPropertyName();
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToOneLinkage($field, $detailPath);
                continue;
            }

            if ($field instanceof AssociationField) {
                $relationships[$field->getPropertyName()] = $this->createToManyLinkage($field, $detailPath);
                continue;
            }

            if ($field instanceof TranslatedField && $definition->getTranslationDefinition()) {
                $field = $definition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            }

            if ($field instanceof JsonField) {
                $attributes[$field->getPropertyName()] = $this->resolveJsonField($field);
                continue;
            }

            $attr = $this->getPropertyByField(\get_class($field));

            /** @var WriteProtected|null $writeProtectedFlag */
            $writeProtectedFlag = $field->getFlag(WriteProtected::class);
            if (\in_array($field->getPropertyName(), ['createdAt', 'updatedAt'], true) || ($writeProtectedFlag && !$writeProtectedFlag->isAllowed(Context::USER_SCOPE))) {
                $attr['readOnly'] = true;
            }

            $attributes[$field->getPropertyName()] = $attr;
        }

        $extensionAttributes = $this->getExtensions($extensions, $detailPath);

        if (!empty($extensionAttributes)) {
            $attributes['extensions'] = [
                'type' => 'object',
                'properties' => $extensionAttributes,
            ];

            foreach ($extensions as $extension) {
                if (!$extension instanceof AssociationField) {
                    continue;
                }

                $relationships[$extension->getPropertyName()] = $extensionAttributes[$extension->getPropertyName()];
            }
        }

        if ($definition->getTranslationDefinition()) {
            foreach ($definition->getTranslationDefinition()->getFields() as $field) {
                if ($field->getPropertyName() === 'translations' || $field->getPropertyName() === 'id') {
                    continue;
                }

                if ($field->is(Required::class) && !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof FkField) {
                    $requiredAttributes[] = $field->getPropertyName();
                }
            }
        }

        $schema = [
            $schemaName => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/resource'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['example' => $definition->getEntityName()],
                            'id' => ['example' => $uuid],
                            'attributes' => [
                                'type' => 'object',
                                'required' => array_unique($requiredAttributes),
                                'properties' => $attributes,
                            ],
                            'links' => [
                                'properties' => [
                                    'self' => [
                                        'type' => 'string',
                                        'format' => 'uri-reference',
                                        'example' => $detailPath,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (\count($relationships)) {
            $schema[$schemaName]['allOf'][1]['properties']['relationships']['properties'] = $relationships;
        }

        $attributes = array_merge(['id' => ['type' => 'string', 'format' => 'uuid']], $attributes);

        foreach ($relationships as $property => $relationship) {
            $relationshipData = $relationship['properties']['data'];
            $type = $relationshipData['type'];
            $entity = '';

            if ($type === 'object') {
                $entity = $relationshipData['properties']['type']['example'];
            } elseif ($type === 'array') {
                $entity = $relationshipData['items']['properties']['type']['example'];
            }

            $attributes[$property] = ['$ref' => '#/components/schemas/' . $entity . '_flat'];
        }

        $schema[$schemaName . '_flat'] = [
            'type' => 'object',
            'properties' => $attributes,
            'required' => array_unique($requiredAttributes),
        ];

        return $schema;
    }

    private function addPathActions(array $openapi, EntityDefinition $definition): array
    {
        $humanReadableName = $this->convertToHumanReadable($definition->getEntityName());

        $schemaName = $definition->getEntityName();
        $path = $this->getResourceUri($definition);

        $responseDataParameter = [
            'name' => '_response',
            'in' => 'query',
            'schema' => [
                'type' => 'string',
            ],
            'allowEmptyValue' => true,
            'description' => 'Data format for response. Empty if none is provided.',
        ];

        $idParameter = [
            'name' => 'id',
            'in' => 'path',
            'schema' => ['type' => 'string', 'format' => 'uuid'],
            'description' => 'Identifier for the ' . $definition->getEntityName(),
            'required' => true,
        ];

        $openapi['paths'][$path] = [
            'get' => [
                'summary' => 'List with basic information of ' . $humanReadableName . ' resources',
                'tags' => [$humanReadableName],
                'parameters' => $this->getDefaultListingParameter(),
                'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()) . 'List',
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
                                                '$ref' => '#/components/schemas/' . $schemaName . '_flat',
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
                'description' => 'Create a new ' . $humanReadableName . ' resources. All required fields must be provided in order to create a new resource successfully.',
                'tags' => [$humanReadableName],
                'operationId' => 'create' . $this->convertToOperationId($definition->getEntityName()),
                'parameters' => [
                    [
                        'name' => '_response',
                        'in' => 'query',
                        'schema' => ['type' => 'string', 'enum' => ['basic', 'detail']],
                        'description' => 'Data format for response. Empty if none is provided.',
                    ],
                ],
                'requestBody' => [
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $definition->getEntityName(),
                            ],
                        ],
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $definition->getEntityName() . '_flat',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    Response::HTTP_CREATED => $this->getDetailResponse($definition->getEntityName()),
                    Response::HTTP_BAD_REQUEST => $this->getResponseRef((string) Response::HTTP_BAD_REQUEST),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
        ];

        $openapi['paths'][$path . '/{id}'] = [
            'get' => [
                'summary' => 'Detailed information about a ' . $humanReadableName . ' resource',
                'operationId' => 'get' . $this->convertToOperationId($definition->getEntityName()),
                'tags' => [$humanReadableName],
                'parameters' => [$idParameter],
                'responses' => [
                    Response::HTTP_OK => $this->getDetailResponse($schemaName),
                    Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
            'patch' => [
                'summary' => 'Partially update information about a ' . $humanReadableName . ' resource',
                'operationId' => 'update' . $this->convertToOperationId($definition->getEntityName()),
                'tags' => [$humanReadableName],
                'parameters' => [$idParameter, $responseDataParameter],
                'requestBody' => [
                    'description' => 'Partially update information about a ' . $humanReadableName . ' resource.',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $definition->getEntityName(),
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
                'operationId' => 'delete' . $this->convertToOperationId($definition->getEntityName()),
                'summary' => 'Delete a ' . $humanReadableName . ' resource',
                'tags' => [$humanReadableName],
                'parameters' => [$idParameter, $responseDataParameter],
                'responses' => [
                    Response::HTTP_NO_CONTENT => $this->getResponseRef((string) Response::HTTP_NO_CONTENT),
                    Response::HTTP_NOT_FOUND => $this->getResponseRef((string) Response::HTTP_NOT_FOUND),
                    Response::HTTP_UNAUTHORIZED => $this->getResponseRef((string) Response::HTTP_UNAUTHORIZED),
                ],
            ],
        ];

        if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            unset($openapi['paths'][$path]['post']);
            unset($openapi['paths'][$path . '/{id}']['patch']);
            unset($openapi['paths'][$path . '/{id}']['delete']);
        }

        return $openapi;
    }

    /**
     * @param ManyToOneAssociationField|OneToOneAssociationField $field
     */
    private function createToOneLinkage($field, string $basePath): array
    {
        return [
            'type' => 'object',
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
                            'format' => 'uuid',
                            'example' => Uuid::randomHex(),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param ManyToManyAssociationField|OneToManyAssociationField|AssociationField $field
     */
    private function createToManyLinkage(AssociationField $field, string $basePath): array
    {
        $associationEntityName = $field->getReferenceDefinition()->getEntityName();

        if ($field instanceof ManyToManyAssociationField) {
            $associationEntityName = $field->getToManyReferenceDefinition()->getEntityName();
        }

        return [
            'type' => 'object',
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
                                'example' => Uuid::randomHex(),
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
                'name' => 'limit',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'Max amount of resources to be returned in a page',
            ],
            [
                'name' => 'page',
                'in' => 'query',
                'schema' => [
                    'type' => 'integer',
                ],
                'description' => 'The page to be returned',
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

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    private function createErrorResponse(int $statusCode, string $title, string $description): array
    {
        $schema = [
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
        ];

        return [
            'description' => $title,
            'content' => [
                'application/vnd.api+json' => $schema,
                'application/json' => $schema,
            ],
        ];
    }

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
                        '$ref' => '#/components/schemas/' . $schemaName . '_flat',
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

    private function getDefaultSchemas(): array
    {
        $defaults = [
            'success' => [
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
            ],
            'failure' => [
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
            ],
            'info' => [
                'type' => 'object',
                'required' => ['meta'],
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                    'links' => ['$ref' => '#/components/schemas/links'],
                    'jsonapi' => ['$ref' => '#/components/schemas/jsonapi'],
                ],
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
            ],
            'relationshipLinks' => [
                'description' => 'A resource object **MAY** contain references to other resource objects ("relationships"). Relationships may be to-one or to-many. Relationships can be specified by including a member in a resource\'s links object.',
                'type' => 'object',
                'additionalProperties' => true,
                'properties' => [
                    'self' => [
                        'description' => 'A `self` member, whose value is a URL for the relationship itself (a "relationship URL"). This URL allows the client to directly manipulate the relationship. For example, it would allow a client to remove an `author` from an `article` without deleting the people resource itself.',
                        '$ref' => '#/components/schemas/link',
                    ],
                    'related' => ['$ref' => '#/components/schemas/link'],
                ],
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
            ],
            'relationshipToOne' => [
                'description' => 'References to other resource objects in a to-one ("relationship"). Relationships can be specified by including a member in a resource\'s links object.',
                '$ref' => '#/components/schemas/linkage',
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
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'additionalProperties' => false,
            ],
            'pagination' => [
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
            ],
            'jsonapi' => [
                'description' => 'An object describing the server\'s implementation',
                'type' => 'object',
                'properties' => [
                    'version' => ['type' => 'string'],
                    'meta' => ['$ref' => '#/components/schemas/meta'],
                ],
                'additionalProperties' => false,
            ],
            'error' => [
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
            ],
        ];

        return $defaults;
    }

    /**
     * @param Field[] $extensions
     */
    private function getExtensions(array $extensions, string $path): array
    {
        $attributes = [];
        foreach ($extensions as $field) {
            $property = $field->getPropertyName();

            if ($field instanceof OneToManyAssociationField) {
                $schema = $this->createToManyLinkage($field, $path);

                /** @var WriteProtected|null $writeProtectedFlag */
                $writeProtectedFlag = $field->getFlag(WriteProtected::class);
                if ($writeProtectedFlag && !$writeProtectedFlag->isAllowed(Context::USER_SCOPE)) {
                    $schema['readOnly'] = true;
                }

                $attributes[$property] = $schema;

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $schema = $this->createToManyLinkage($field, $path);

                /** @var WriteProtected|null $writeProtectedFlag */
                $writeProtectedFlag = $field->getFlag(WriteProtected::class);
                if ($writeProtectedFlag && !$writeProtectedFlag->isAllowed(Context::USER_SCOPE)) {
                    $schema['readOnly'] = true;
                }

                $attributes[$property] = $schema;

                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $schema = $this->createToOneLinkage($field, $path);

                /** @var WriteProtected|null $writeProtectedFlag */
                $writeProtectedFlag = $field->getFlag(WriteProtected::class);
                if ($writeProtectedFlag && !$writeProtectedFlag->isAllowed(Context::USER_SCOPE)) {
                    $schema['readOnly'] = true;
                }

                $attributes[$property] = $schema;

                continue;
            }

            if ($field instanceof JsonField) {
                $schema = $this->resolveJsonField($field);

                /** @var WriteProtected|null $writeProtectedFlag */
                $writeProtectedFlag = $field->getFlag(WriteProtected::class);
                if ($writeProtectedFlag && !$writeProtectedFlag->isAllowed(Context::USER_SCOPE)) {
                    $schema['readOnly'] = true;
                }

                $attributes[$property] = $schema;

                continue;
            }
        }

        return $attributes;
    }

    private function createSecurity(bool $forSalesChannel): array
    {
        if ($forSalesChannel) {
            return ['ApiKey' => []];
        }

        return ['oAuth' => ['write']];
    }

    private function createSecurityScheme(bool $forSalesChannel): array
    {
        if ($forSalesChannel) {
            return [
                'ApiKey' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => PlatformRequest::HEADER_ACCESS_KEY,
                ],
            ];
        }

        $url = getenv('APP_URL');

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
                ],
            ],
        ];
    }

    private function containsSalesChannelDefinition(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
                return true;
            }
        }

        return false;
    }
}
