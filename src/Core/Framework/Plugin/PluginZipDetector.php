<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

class PluginZipDetector
{
    public function isPlugin(\ZipArchive $archive): bool
    {
        $entry = $archive->statIndex(0);

        $pluginName = explode('/', $entry['name'])[0];
        $composerFile = $pluginName . '/composer.json';

        $statComposerFile = $archive->statName($composerFile);

        return $statComposerFile !== false;
    }
}
