<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Context;

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

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var Client
     */
    private $client;

    public function __construct(
        string $pluginPath,
        PluginZipDetector $pluginZipDetector,
        PluginExtractor $pluginExtractor,
        PluginService $pluginService,
        Client $client
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginZipDetector = $pluginZipDetector;
        $this->pluginExtractor = $pluginExtractor;
        $this->pluginService = $pluginService;
        $this->client = $client;
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

    public function downloadStorePlugin(array $data, Context $context): bool
    {
        $location = $data['location'];

        $tempFileName = tempnam(sys_get_temp_dir(), 'store-plugin');

        $response = $this->client->get($location, ['sink' => $tempFileName]);

        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $this->extractPluginZip($tempFileName);

        $this->pluginService->refreshPlugins($context, new NullIO());

        return true;
    }

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

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }

        rmdir($path);
    }
}
