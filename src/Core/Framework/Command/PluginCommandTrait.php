<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Style\SymfonyStyle;

trait PluginCommandTrait
{
    abstract public function getPluginService(): PluginService;

    public function displayHeader(SymfonyStyle $io): void
    {
        $io->title('Shopware Plugin Manager');
    }

    /**
     * @throws PluginNotFoundException
     *
     * @return PluginEntity[]
     */
    public function parsePluginArgument(array $arguments, Context $context): array
    {
        $plugins = array_unique($arguments);
        $pluginStructs = [];

        foreach ($plugins as $pluginName) {
            $pluginStructs[$pluginName] = $this->getPluginService()->getPluginByName($pluginName, $context);
        }

        return $pluginStructs;
    }

    /**
     * @param PluginEntity[] $pluginStructs
     *
     * @return string[]
     */
    public function formatPluginList(array $pluginStructs): array
    {
        return array_map(function (PluginEntity $plugin) {
            return sprintf('%s (v%s)', $plugin->getLabel(), $plugin->getVersion());
        }, $pluginStructs);
    }
}
