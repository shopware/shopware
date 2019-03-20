<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

class PluginZipDetector
{
    public function isPlugin(\ZipArchive $archive): bool
    {
        $entry = $archive->statIndex(0);

        $pluginName = explode('/', $entry['name'])[0];
        $baseClass = $pluginName . '/' . $pluginName . '.php';
        $composerFile = $pluginName . '/composer.json';

        $statBaseClass = $archive->statName($baseClass);
        $statComposerFile = $archive->statName($composerFile);

        return $statBaseClass !== false && $statComposerFile !== false;
    }
}
