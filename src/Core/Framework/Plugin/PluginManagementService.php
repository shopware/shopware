<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
#[Package('core')]
class PluginManagementService
{
    final public const PLUGIN = 'plugin';
    final public const APP = 'app';

    public function __construct(
        private readonly string $projectDir,
        private readonly PluginZipDetector $pluginZipDetector,
        private readonly PluginExtractor $pluginExtractor,
        private readonly PluginService $pluginService,
        private readonly Filesystem $filesystem,
        private readonly CacheClearer $cacheClearer,
        private readonly Client $client
    ) {
    }

    public function extractPluginZip(string $file, bool $delete = true, ?string $storeType = null): string
    {
        $archive = ZipUtils::openZip($file);

        if ($storeType) {
            $this->pluginExtractor->extract($archive, $delete, $storeType);
            if ($storeType === self::PLUGIN) {
                $this->cacheClearer->clearContainerCache();
            }

            return $storeType;
        }

        if ($this->pluginZipDetector->isPlugin($archive)) {
            $this->pluginExtractor->extract($archive, $delete, self::PLUGIN);
            $this->cacheClearer->clearContainerCache();

            return self::PLUGIN;
        }

        if ($this->pluginZipDetector->isApp($archive)) {
            $this->pluginExtractor->extract($archive, $delete, self::APP);

            return self::APP;
        }

        throw new NoPluginFoundInZipException($file);
    }

    public function uploadPlugin(UploadedFile $file, Context $context): void
    {
        /** @var string $tempFileName */
        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        /** @var string $tempRealPath */
        $tempRealPath = realpath($tempFileName);
        $tempDirectory = \dirname($tempRealPath);

        $tempFile = $file->move($tempDirectory, $tempFileName);

        $type = $this->extractPluginZip($tempFile->getPathname());

        if ($type === self::PLUGIN) {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function downloadStorePlugin(PluginDownloadDataStruct $location, Context $context): void
    {
        /** @var string $tempFileName */
        $tempFileName = tempnam(sys_get_temp_dir(), 'store-plugin');

        try {
            $response = $this->client->request('GET', $location->getLocation(), ['sink' => $tempFileName]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \RuntimeException();
            }
        } catch (\Exception) {
            throw new StoreNotAvailableException();
        }

        $this->extractPluginZip($tempFileName, true, $location->getType());

        if ($location->getType() === self::PLUGIN) {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function deletePlugin(PluginEntity $plugin, Context $context): void
    {
        if ($plugin->getManagedByComposer()) {
            throw PluginException::cannotDeleteManaged($plugin->getName());
        }

        $path = $this->projectDir . '/' . $plugin->getPath();
        $this->filesystem->remove($path);

        $this->pluginService->refreshPlugins($context, new NullIO());
    }
}
