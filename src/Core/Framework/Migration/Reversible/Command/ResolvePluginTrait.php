<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

/**
 * @internal
 */
#[Package('framework')]
trait ResolvePluginTrait
{
    /**
     * Resolves a plugin by name, accepting a unique prefix, matching database:create-migration.
     */
    private function resolvePlugin(KernelPluginCollection $plugins, string $pluginName): Plugin
    {
        $matches = array_filter($plugins->all(), static fn (Plugin $plugin) => mb_strpos($plugin->getName(), $pluginName) === 0);

        if ($matches === []) {
            throw MigrationException::pluginNotFound($pluginName);
        }

        if (\count($matches) > 1) {
            $exact = array_filter($matches, static fn (Plugin $plugin) => $pluginName === $plugin->getName());

            if (\count($exact) !== 1) {
                throw MigrationException::moreThanOnePluginFound($pluginName, array_keys($matches));
            }

            $matches = $exact;
        }

        return array_values($matches)[0];
    }
}
