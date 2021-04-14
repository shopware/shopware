<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\NoPluginFoundInZipException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class PluginManagementService
{
    private string $projectDir;

    private PluginZipDetector $pluginZipDetector;

    private PluginExtractor $pluginExtractor;

    private PluginService $pluginService;

    private Filesystem $filesystem;

    private CacheClearer $cacheClearer;

    private Client $client;

    public function __construct(
        string $projectDir,
        PluginZipDetector $pluginZipDetector,
        PluginExtractor $pluginExtractor,
        PluginService $pluginService,
        Filesystem $filesystem,
        CacheClearer $cacheClearer,
        Client $client
    ) {
        $this->projectDir = $projectDir;
        $this->pluginZipDetector = $pluginZipDetector;
        $this->pluginExtractor = $pluginExtractor;
        $this->pluginService = $pluginService;
        $this->filesystem = $filesystem;
        $this->cacheClearer = $cacheClearer;
        $this->client = $client;
    }

    public function extractPluginZip(string $file, bool $delete = true, ?string $storeType = null): string
    {
        $archive = ZipUtils::openZip($file);

        if ($storeType) {
            $this->pluginExtractor->extract($archive, $delete, $storeType);

            if ($storeType === 'plugin') {
                $this->cacheClearer->clearContainerCache();
            }

            return $storeType;
        }

        if ($this->pluginZipDetector->isPlugin($archive)) {
            $this->pluginExtractor->extract($archive, $delete, 'plugin');
            $this->cacheClearer->clearContainerCache();

            return 'plugin';
        } elseif ($this->pluginZipDetector->isApp($archive)) {
            $this->pluginExtractor->extract($archive, $delete, 'app');

            return 'app';
        }

        throw new NoPluginFoundInZipException($file);
    }

    public function uploadPlugin(UploadedFile $file, Context $context): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        $tempDirectory = \dirname(realpath($tempFileName));

        $tempFile = $file->move($tempDirectory, $tempFileName);

        $type = $this->extractPluginZip($tempFile->getPathname());

        if ($type === 'plugin') {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function downloadStorePlugin(PluginDownloadDataStruct $location, Context $context): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), 'store-plugin');

        try {
            $response = $this->client->request('GET', $location->getLocation(), ['sink' => $tempFileName]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \RuntimeException();
            }
        } catch (\Exception $e) {
            throw new StoreNotAvailableException();
        }

        $this->extractPluginZip($tempFileName, true, $location->getType());

        if ($location->getType() === 'plugin') {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function deletePlugin(PluginEntity $plugin, Context $context): void
    {
        $path = $this->projectDir . '/' . $plugin->getPath();
        $this->filesystem->remove($path);

        $this->pluginService->refreshPlugins($context, new NullIO());
    }
}
