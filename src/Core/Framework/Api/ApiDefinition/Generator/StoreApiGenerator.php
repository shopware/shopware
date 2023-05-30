<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use http\Exception\RuntimeException;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
#[Package('core')]
class StoreApiGenerator implements ApiDefinitionGeneratorInterface
{
    final public const FORMAT = 'openapi-3';
    private const OPERATION_KEYS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    private readonly string $schemaPath;

    /**
     * @param array{Framework: array{path: string}} $bundles
     *
     * @internal
     */
    public function __construct(
        private readonly OpenApiSchemaBuilder $openApiBuilder,
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        array $bundles,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection
    ) {
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/StoreApi';
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT && $api === DefinitionService::STORE_API;
    }

    public function generate(array $definitions, string $api, string $apiType): array
    {
        $openApi = new OpenApi([]);
        $this->openApiBuilder->enrich($openApi, $api);

        $forSalesChannel = $api === DefinitionService::STORE_API;

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$definition instanceof EntityDefinition) {
                continue;
            }

            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $onlyReference = $this->shouldIncludeReferenceOnly($definition, $forSalesChannel);

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel, $onlyReference);

            $openApi->components->merge($schema);
        }

        $this->addGeneralInformation($openApi);
        $this->addContentTypeParameter($openApi);

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $data['paths'] ??= [];

        $schemaPaths = [$this->schemaPath];
        $schemaPaths = array_merge($schemaPaths, $this->bundleSchemaPathCollection->getSchemaPaths($api));

        $loader = new OpenApiFileLoader($schemaPaths);

        /** @var OpenApiSpec $finalSpecs */
        $finalSpecs = array_replace_recursive($data, $loader->loadOpenapiSpecification());

        return $finalSpecs;
    }

    /**
     * {@inheritdoc}
     *
     * @param list<EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     *
     * @return never
     */
    public function getSchema(array $definitions): array
    {
        throw new RuntimeException();
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

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    private function addGeneralInformation(OpenApi $openApi): void
    {
        $openApi->info->description = 'This endpoint reference contains an overview of all endpoints comprising the Shopware Store API';
    }

    private function addContentTypeParameter(OpenApi $openApi): void
    {
        $openApi->components->parameters = [
            new Parameter([
                'parameter' => 'contentType',
                'name' => 'Content-Type',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Content type of the request',
            ]),
            new Parameter([
                'parameter' => 'accept',
                'name' => 'Accept',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Accepted response content types',
            ]),
        ];

        if (!is_iterable($openApi->paths)) {
            return;
        }

        foreach ($openApi->paths as $path) {
            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $path->$key; /* @phpstan-ignore-line */

                if (!$operation instanceof Operation) {
                    continue;
                }

                if (!\is_array($operation->parameters)) {
                    $operation->parameters = [];
                }

                array_push($operation->parameters, [
                    '$ref' => '#/components/parameters/contentType',
                ], [
                    '$ref' => '#/components/parameters/accept',
                ]);
            }
        }
    }
}
