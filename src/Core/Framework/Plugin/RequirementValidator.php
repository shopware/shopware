<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Plugin\Exception\PluginToPluginRequirementException;
use Shopware\Core\Framework\Plugin\Exception\PluginToShopwareCompatibilityException;

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
     * @param PluginEntity[] $availablePlugins
     *
     * @throws PluginToPluginRequirementException
     * @throws PluginToShopwareCompatibilityException
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
     * @throws PluginToShopwareCompatibilityException
     */
    private function assertShopwareVersion(array $compatibility, string $shopwareVersion): void
    {
        if ($shopwareVersion === '___VERSION___') {
            return;
        }

        if ($this->checkVersionForBlacklist($compatibility['blacklist'], $shopwareVersion)) {
            throw new PluginToShopwareCompatibilityException(
                sprintf('Shopware version %s is blacklisted by this plugin', $shopwareVersion)
            );
        }

        $minimumShopwareVersion = $compatibility['minVersion'];
        if ($this->checkMinimumVersion($shopwareVersion, $minimumShopwareVersion)) {
            throw new PluginToShopwareCompatibilityException(
                sprintf('This plugin requires at least Shopware version %s', $minimumShopwareVersion)
            );
        }

        $maximumShopwareVersion = $compatibility['maxVersion'];
        if ($this->checkMaximumVersion($shopwareVersion, $maximumShopwareVersion)) {
            throw new PluginToShopwareCompatibilityException(
                sprintf('This plugin is only compatible with Shopware version smaller or equal to %s', $maximumShopwareVersion)
            );
        }
    }

    /**
     * @param array[]        $requiredPlugins
     * @param PluginEntity[] $availablePlugins
     *
     * @throws PluginToPluginRequirementException
     */
    private function assertRequiredPlugins(array $requiredPlugins, array $availablePlugins): void
    {
        foreach ($requiredPlugins as $requiredPlugin) {
            $requiredPluginName = $requiredPlugin['pluginName'];
            $availablePlugin = $availablePlugins[$requiredPluginName] ?? null;

            if ($availablePlugin === null) {
                throw new PluginToPluginRequirementException(
                    sprintf('Required plugin %s was not found', $requiredPluginName)
                );
            }

            if ($availablePlugin->getInstallationDate() === null) {
                throw new PluginToPluginRequirementException(
                    sprintf('Required plugin %s is not installed', $requiredPluginName)
                );
            }

            if ($availablePlugin->getActive() === false) {
                throw  new PluginToPluginRequirementException(
                    sprintf('Required plugin %s is not active', $requiredPluginName)
                );
            }

            $availablePluginVersion = $availablePlugin->getVersion();
            $availablePluginName = $availablePlugin->getName();

            if ($this->checkVersionForBlacklist($requiredPlugin['blacklist'], $availablePluginVersion)) {
                throw new PluginToPluginRequirementException(
                    sprintf('Required plugin %s with version %s is blacklisted', $availablePluginName, $availablePluginVersion)
                );
            }

            $minimumPluginVersion = $requiredPlugin['minVersion'];
            if ($this->checkMinimumVersion($availablePluginVersion, $minimumPluginVersion)) {
                throw new PluginToPluginRequirementException(
                    sprintf('Version %s of plugin %s is required.', $minimumPluginVersion, $availablePluginName)
                );
            }

            $maximumPluginVersion = $requiredPlugin['maxVersion'];
            if ($this->checkMaximumVersion($availablePluginVersion, $maximumPluginVersion)) {
                throw new PluginToPluginRequirementException(
                    sprintf('Plugin is only compatible with plugin %s version smaller or equal to %s', $availablePluginName, $maximumPluginVersion)
                );
            }
        }
    }

    private function checkVersionForBlacklist(array $blacklist, string $versionToCheck): bool
    {
        return \in_array($versionToCheck, $blacklist, true);
    }

    private function checkMinimumVersion(string $currentVersion, $minimumVersion): bool
    {
        return $minimumVersion !== '' && !$this->assertVersion($currentVersion, $minimumVersion, '>=');
    }

    private function checkMaximumVersion(string $currentVersion, $maximumVersion): bool
    {
        return $maximumVersion !== '' && !$this->assertVersion($currentVersion, $maximumVersion, '<=');
    }

    private function assertVersion(string $version, string $required, string $operator): bool
    {
        return version_compare($version, $required, $operator);
    }
}
