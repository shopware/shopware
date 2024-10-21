<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
#[Package('core')]
class PluginZipDetector
{
    /**
     * @return PluginManagementService::PLUGIN|PluginManagementService::APP
     */
    public function detect(string $zipFilePath): string
    {
        try {
            $archive = ZipUtils::openZip($zipFilePath);
        } catch (PluginExtractionException $e) {
            throw PluginException::noPluginFoundInZip($zipFilePath);
        }

        try {
            return match (true) {
                $this->isPlugin($archive) => PluginManagementService::PLUGIN,
                $this->isApp($archive) => PluginManagementService::APP,
                default => throw PluginException::noPluginFoundInZip($zipFilePath),
            };
        } finally {
            $archive->close();
        }
    }

    public function isPlugin(\ZipArchive $archive): bool
    {
        $entry = $archive->statIndex(0);
        if ($entry === false) {
            return false;
        }

        $pluginName = explode('/', (string) $entry['name'])[0];
        $composerFile = $pluginName . '/composer.json';
        $manifestFile = $pluginName . '/manifest.xml';

        $statComposerFile = $archive->statName($composerFile);
        $statManifestFile = $archive->statName($manifestFile);

        return $statComposerFile !== false && $statManifestFile === false;
    }

    public function isApp(\ZipArchive $archive): bool
    {
        $entry = $archive->statIndex(0);
        if ($entry === false) {
            return false;
        }

        $pluginName = explode('/', (string) $entry['name'])[0];
        $manifestFile = $pluginName . '/manifest.xml';

        $statManifestFile = $archive->statName($manifestFile);

        return $statManifestFile !== false;
    }
}
