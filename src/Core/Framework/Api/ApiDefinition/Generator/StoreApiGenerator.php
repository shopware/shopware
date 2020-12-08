<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

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
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/SalesChannel/Schema/';
        $this->openApi3Generator = $openApi3Generator;
    }

    public function supports(string $format, int $version, string $api): bool
    {
        return $format === self::FORMAT && $api === DefinitionService::STORE_API;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $definitions, int $version, string $api): array
    {
        $openApi = $this->openApiLoader->load($api);
        $this->openApiBuilder->enrich($openApi, $api, $version);
        $forSalesChannel = \in_array($api, [DefinitionService::SALES_CHANNEL_API, DefinitionService::STORE_API], true);

        ksort($definitions);

        foreach ($definitions as $definition) {
            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $onlyReference = $this->shouldIncludeReferenceOnly($definition, $forSalesChannel);

            $schema = $this->definitionSchemaBuilder->getSchemaByDefinition($definition, $this->getResourceUri($definition), $forSalesChannel, $version, $onlyReference);

            $openApi->components->merge($schema);
        }

        $data = json_decode($openApi->toJson(), true);

        $finder = (new Finder())->in($this->schemaPath)->name('*.json');

        foreach ($finder as $item) {
            $name = str_replace('.json', '', $item->getFilename());

            $readData = json_decode(file_get_contents($item->getPathname()), true);
            $data['components']['schemas'][$name] = $readData;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(array $definitions, int $version): array
    {
        return $this->openApi3Generator->getSchema($definitions, $version);
    }

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
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
