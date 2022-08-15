<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\OpenApi;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
class OpenApi3Generator implements ApiDefinitionGeneratorInterface
{
    public const FORMAT = 'openapi-3';

    private OpenApiSchemaBuilder $openApiBuilder;

    private OpenApiPathBuilder $pathBuilder;

    private OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder;

    private OpenApiLoader $openApiLoader;

    private string $schemaPath;

    private PluginSchemaPathCollection $pluginSchemaPathCollection;

    /**
     * @param array{Framework: array{path: string}} $bundles
     */
    public function __construct(
        OpenApiSchemaBuilder $openApiBuilder,
        OpenApiPathBuilder $pathBuilder,
        OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        OpenApiLoader $openApiLoader,
        array $bundles,
        PluginSchemaPathCollection $pluginSchemaPathCollection
    ) {
        $this->openApiBuilder = $openApiBuilder;
        $this->pathBuilder = $pathBuilder;
        $this->definitionSchemaBuilder = $definitionSchemaBuilder;
        $this->openApiLoader = $openApiLoader;
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/AdminApi';
        $this->pluginSchemaPathCollection = $pluginSchemaPathCollection;
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT;
    }

    public function generate(array $definitions, string $api, string $apiType = DefinitionService::TypeJsonApi): array
    {
        $forSalesChannel = $this->containsSalesChannelDefinition($definitions);

        $openApi = new OpenApi([]);
        if (!Feature::isActive('v6.5.0.0')) {
            $openApi = $this->openApiLoader->load($api);
        }
        $this->openApiBuilder->enrich($openApi, $api);

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            switch ($apiType) {
                case DefinitionService::TypeJson:
                    $onlyFlat = true;

                    break;
                case DefinitionService::TypeJsonApi:
                default:
                    $onlyFlat = $this->shouldIncludeReferenceOnly($definition, $forSalesChannel);
            }

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                $forSalesChannel,
                $onlyFlat,
                $apiType
            );

            $openApi->components->merge($schema);

            if ($onlyFlat) {
                continue;
            }

            if ($apiType === DefinitionService::TypeJsonApi) {
                $openApi->merge($this->pathBuilder->getPathActions($definition, $this->getResourceUri($definition)));
                $openApi->merge([$this->pathBuilder->getTag($definition)]);
            }
        }

        $data = json_decode($openApi->toJson(), true);
        $data['paths'] = $data['paths'] ?? [];

        $schemaPaths = [$this->schemaPath];
        $schemaPaths = $schemaPaths + $this->pluginSchemaPathCollection->getSchemaPaths($api);

        $loader = new OpenApiFileLoader($schemaPaths);

        /** @var OpenApiSpec $finalSpecs */
        $finalSpecs = array_replace_recursive($data, $loader->loadOpenapiSpecification());

        return $finalSpecs;
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

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel);
            $schema = array_shift($schema);
            if ($schema === null) {
                throw new \RuntimeException('Invalid schema detected. Aborting');
            }
            $schema = json_decode($schema->toJson(), true);
            $schema = $schema['allOf'][1]['properties'];

            $relationships = [];
            if (\array_key_exists('relationships', $schema)) {
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
                        'pattern' => '^[0-9a-f]{32}$',
                    ],
                ],
                $schema,
                $relationships
            );

            if (\array_key_exists('extensions', $properties)) {
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
                'translatable' => $definition->getFields()->filterInstance(TranslatedField::class)->getKeys(),
                'properties' => $properties,
            ];
        }

        return $schemaDefinitions;
    }

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    /**
     * @param list<EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     */
    private function containsSalesChannelDefinition(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            if (is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
                return true;
            }
        }

        return false;
    }

    private function shouldDefinitionBeIncluded(EntityDefinition $definition): bool
    {
        if (preg_match('/_translation$/', $definition->getEntityName())) {
            return false;
        }

        if (mb_strpos($definition->getEntityName(), 'version') === 0) {
            return false;
        }

        return true;
    }

    private function shouldIncludeReferenceOnly(EntityDefinition $definition, bool $forSalesChannel): bool
    {
        $class = new \ReflectionClass($definition);
        if ($class->isSubclassOf(MappingEntityDefinition::class)) {
            return true;
        }

        if ($forSalesChannel && !is_subclass_of($definition, SalesChannelDefinitionInterface::class)) {
            return true;
        }

        return false;
    }
}
