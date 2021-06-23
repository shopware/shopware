<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\Finder\Finder;

class StoreApiGenerator implements ApiDefinitionGeneratorInterface
{
    public const FORMAT = 'openapi-3';
    private const OPERATION_KEYS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    /**
     * @var OpenApiSchemaBuilder
     */
    private $openApiBuilder;

    /**
     * @var OpenApiDefinitionSchemaBuilder
     */
    private $definitionSchemaBuilder;

    /**
     * @var OpenApiLoader
     */
    private $openApiLoader;

    /**
     * @var string
     */
    private $schemaPath;

    /**
     * @var OpenApi3Generator
     */
    private $openApi3Generator;

    public function __construct(
        OpenApiSchemaBuilder $openApiBuilder,
        OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        OpenApiLoader $openApiLoader,
        array $bundles,
        OpenApi3Generator $openApi3Generator
    ) {
        $this->openApiBuilder = $openApiBuilder;
        $this->definitionSchemaBuilder = $definitionSchemaBuilder;
        $this->openApiLoader = $openApiLoader;
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/StoreApi/';
        $this->openApi3Generator = $openApi3Generator;
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT && $api === DefinitionService::STORE_API;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $definitions, string $api, string $apiType): array
    {
        $openApi = $this->openApiLoader->load($api);
        $this->openApiBuilder->enrich($openApi, $api);
        $forSalesChannel = $api === DefinitionService::STORE_API;

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $onlyReference = $this->shouldIncludeReferenceOnly($definition, $forSalesChannel);

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel, $onlyReference);

            $openApi->components->merge($schema);
        }

        $this->addGeneralInformation($openApi);
        $this->addContentTypeParameter($openApi);

        $data = json_decode($openApi->toJson(), true);

        $finder = (new Finder())->in($this->schemaPath)->name('*.json');

        foreach ($finder as $item) {
            $name = str_replace('.json', '', $item->getFilename());

            $readData = json_decode((string) file_get_contents($item->getPathname()), true);
            $data['components']['schemas'][$name] = $readData;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(array $definitions): array
    {
        return $this->openApi3Generator->getSchema($definitions);
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

        foreach ($openApi->paths as $path) {
            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $path->$key;

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
