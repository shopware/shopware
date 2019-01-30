<?php
namespace Shopware\Core\Framework\Plugin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PluginInstallerService
{
    /**
     * @var string
     */
    private $pluginPath;

    /**
     * @var PluginZipDetector
     */
    private $pluginZipDetector;

    /**
     * @var PluginExtractor
     */
    private $pluginExtractor;

    public function __construct(
        string $pluginPath,
        PluginZipDetector $pluginZipDetector,
        PluginExtractor $pluginExtractor
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginZipDetector = $pluginZipDetector;
        $this->pluginExtractor = $pluginExtractor;
    }

    /**
     * @param string $file
     *
     * @throws \Exception
     */
    public function extractPluginZip($file): void
    {
        $archive = ZipUtils::openZip($file);

        if ($this->pluginZipDetector->isPlugin($archive)) {
            $this->pluginExtractor->extract($archive);
        } else {
            throw new \RuntimeException('No Plugin found in archive.');
        }
    }

    /**
     * @param PluginEntity $plugin
     */
    public function deletePlugin(PluginEntity $plugin): void
    {
        $path = $this->pluginPath . DIRECTORY_SEPARATOR . $plugin->getName();
        $this->removeDirectory($path);
    }

    /**
     * @param string $path
     */
    private function removeDirectory($path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }

        rmdir($path);
    }
}
