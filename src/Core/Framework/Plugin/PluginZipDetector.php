<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class PluginZipDetector
{
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
