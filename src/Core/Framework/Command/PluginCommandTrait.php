<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Plugin\PluginManager;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Console\Style\SymfonyStyle;

trait PluginCommandTrait
{
    abstract public function getPluginManager(): PluginManager;

    public function displayHeader(SymfonyStyle $io): void
    {
        $io->title('Shopware Plugin Manager');
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    public function parsePluginArgument(array $arguments): array
    {
        $plugins = array_unique($arguments);
        $pluginStructs = [];

        foreach ($plugins as $pluginName) {
            $pluginStructs[$pluginName] = $this->getPluginManager()->getPluginByName($pluginName);
        }

        return $pluginStructs;
    }

    /**
     * @param Plugin[] $pluginStructs
     *
     * @return string[]
     */
    public function formatPluginList(array $pluginStructs): array
    {
        return array_map(function (Plugin $plugin) {
            return sprintf('%s (v%s)', $plugin->getLabel(), $plugin->getVersion());
        }, $pluginStructs);
    }
}
