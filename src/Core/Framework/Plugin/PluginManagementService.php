<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
        if ($storeType) {
            $this->pluginExtractor->extract($file, $delete, $storeType);
            if ($storeType === self::PLUGIN) {
                $this->cacheClearer->clearContainerCache();
            }

            return $storeType;
        }

        $type = $this->pluginZipDetector->detect($file);

        match ($type) {
            self::PLUGIN => $this->extractPlugin($file, $delete),
            self::APP => $this->extractApp($file, $delete),
        };

        return $type;
    }

    public function uploadPlugin(UploadedFile $file, Context $context): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        if (!\is_string($tempFileName)) {
            throw PluginException::cannotCreateTemporaryDirectory(sys_get_temp_dir(), $file->getClientOriginalName());
        }
        $tempRealPath = realpath($tempFileName);
        \assert(\is_string($tempRealPath));
        $tempDirectory = \dirname($tempRealPath);

        $tempFile = $file->move($tempDirectory, $tempFileName);

        $type = $this->extractPluginZip($tempFile->getPathname());

        if ($type === self::PLUGIN) {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function downloadStorePlugin(PluginDownloadDataStruct $location, Context $context): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), 'store-plugin');
        if (!\is_string($tempFileName)) {
            throw PluginException::cannotCreateTemporaryDirectory(sys_get_temp_dir(), 'store-plugin');
        }

        try {
            $response = $this->client->request('GET', $location->getLocation(), ['sink' => $tempFileName]);
        } catch (\Exception) {
            throw PluginException::storeNotAvailable();
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw PluginException::storeNotAvailable();
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

    private function extractPlugin(string $fileName, bool $delete): void
    {
        $this->pluginExtractor->extract($fileName, $delete, self::PLUGIN);
        $this->cacheClearer->clearContainerCache();
    }

    private function extractApp(string $fileName, bool $delete): void
    {
        $this->pluginExtractor->extract($fileName, $delete, self::APP);
    }
}
