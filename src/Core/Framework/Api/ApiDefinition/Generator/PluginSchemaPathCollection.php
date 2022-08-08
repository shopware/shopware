<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

/**
 * @internal
 * @phpstan-import-type Api from DefinitionService
 */
class PluginSchemaPathCollection
{
    private KernelPluginCollection $plugins;

    public function __construct(KernelPluginCollection $plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * @phpstan-param Api $api
     *
     * @return list<string>
     */
    public function getSchemaPaths(string $api): array
    {
        $apiFolder = $api === DefinitionService::API ? 'AdminApi' : 'StoreApi';
        $openApiDirs = [];
        foreach ($this->plugins->getActives() as $plugin) {
            $openApiDirs[] = $plugin->getPath() . '/Resources/Schema/' . $apiFolder;
        }

        return $openApiDirs;
    }
}
