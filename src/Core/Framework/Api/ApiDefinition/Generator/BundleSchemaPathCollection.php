<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 * @phpstan-import-type Api from DefinitionService
 */
class BundleSchemaPathCollection
{
    /**
     * @var iterable<Bundle>
     */
    private iterable $bundles;

    /**
     * @param iterable<Bundle> $plugins
     */
    public function __construct(iterable $plugins)
    {
        $this->bundles = $plugins;
    }

    /**
     * @phpstan-param Api $api
     *
     * @return string[]
     */
    public function getSchemaPaths(string $api): array
    {
        $apiFolder = $api === DefinitionService::API ? 'AdminApi' : 'StoreApi';
        $openApiDirs = [];
        foreach ($this->bundles as $bundle) {
            $path = $bundle->getPath() . '/Resources/Schema/' . $apiFolder;
            if (!is_dir($path)) {
                continue;
            }
            $openApiDirs[] = $path;
        }

        return $openApiDirs;
    }
}
