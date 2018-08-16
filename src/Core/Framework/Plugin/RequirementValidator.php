<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

class RequirementValidator
{
    /**
     * @var XmlPluginInfoReader
     */
    private $infoReader;

    public function __construct(XmlPluginInfoReader $infoReader)
    {
        $this->infoReader = $infoReader;
    }

    /**
     * @param string         $pluginXmlFile    File path to the plugin.xml
     * @param string         $shopwareVersion  current shopware version
     * @param PluginStruct[] $availablePlugins
     */
    public function validate(string $pluginXmlFile, string $shopwareVersion, array $availablePlugins): void
    {
        if (!is_file($pluginXmlFile)) {
            return;
        }

        $info = $this->infoReader->read($pluginXmlFile);

        if (isset($info['compatibility'])) {
            $this->assertShopwareVersion($info['compatibility'], $shopwareVersion);
        }

        if (isset($info['requiredPlugins'])) {
            $this->assertRequiredPlugins($info['requiredPlugins'], $availablePlugins);
        }
    }

    /**
     * @param string $version
     * @param string $required
     * @param string $operator
     *
     * @return bool
     */
    private function assertVersion($version, $required, $operator): bool
    {
        if ($version === '___VERSION___') {
            return true;
        }

        return version_compare($version, $required, $operator);
    }

    /**
     * @param array  $compatibility
     * @param string $shopwareVersion
     *
     * @throws \Exception
     */
    private function assertShopwareVersion($compatibility, $shopwareVersion): void
    {
        if (in_array($shopwareVersion, $compatibility['blacklist'])) {
            throw new \RuntimeException(sprintf('Shopware version %s is blacklisted by the plugin', $shopwareVersion));
        }

        $min = $compatibility['minVersion'];
        if (strlen($min) > 0 && !$this->assertVersion($shopwareVersion, $min, '>=')) {
            throw new \RuntimeException(sprintf('Plugin requires at least Shopware version %s', $min));
        }

        $max = $compatibility['maxVersion'];
        if (strlen($max) > 0 && !$this->assertVersion($shopwareVersion, $max, '<=')) {
            throw new \RuntimeException(sprintf('Plugin is only compatible with Shopware version <= %s', $max));
        }
    }

    /**
     * @param array[]        $requiredPlugins
     * @param PluginStruct[] $availablePlugins
     *
     * @throws \Exception
     */
    private function assertRequiredPlugins(array $requiredPlugins, array $availablePlugins): void
    {
        foreach ($requiredPlugins as $requiredPlugin) {
            $plugin = $availablePlugins[$requiredPlugin['pluginName']] ?? null;

            if (!$plugin) {
                throw new \RuntimeException(sprintf('Required plugin %s was not found', $requiredPlugin['pluginName']));
            }

            if ($plugin->getInstallationDate() === null) {
                throw  new \RuntimeException(sprintf('Required plugin %s is not installed', $requiredPlugin['pluginName']));
            }

            if (!$plugin->getActive()) {
                throw  new \RuntimeException(sprintf('Required plugin %s is not active', $requiredPlugin['pluginName']));
            }

            if (in_array($plugin->getVersion(), $requiredPlugin['blacklist'], true)) {
                throw new \RuntimeException(sprintf('Required plugin %s with version %s is blacklisted', $plugin->getName(), $plugin->getVersion()));
            }

            $min = $requiredPlugin['minVersion'];
            if (strlen($min) > 0 && !$this->assertVersion($plugin->getVersion(), $min, '>=')) {
                throw new \RuntimeException(sprintf('Version %s of plugin %s is required.', $min, $plugin->getName()));
            }

            $max = $requiredPlugin['maxVersion'];
            if (strlen($max) > 0 && !$this->assertVersion($plugin->getVersion(), $max, '<=')) {
                throw new \RuntimeException(sprintf('Plugin is only compatible with Plugin %s version <= %s', $plugin->getName(), $max));
            }
        }
    }
}
