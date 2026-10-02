<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginManagementService
{
    final public const PLUGIN = 'plugin';
    final public const APP = 'app';

    public function __construct(
        private readonly string $projectDir,
        private readonly PluginZipDetector $pluginZipDetector,
        private readonly ExtensionExtractor $extensionExtractor,
        private readonly PluginService $pluginService,
        private readonly Filesystem $filesystem,
        private readonly CacheClearer $cacheClearer,
        private readonly Client $client,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function extractPluginZip(string $file, bool $delete = true, ?string $storeType = null): string
    {
        if ($storeType) {
            $this->extensionExtractor->extract($file, $delete, $storeType);
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

        $type = $this->pluginZipDetector->detect($tempFile->getPathname());
        $metadata = $type === self::PLUGIN ? $this->readUploadMetadata($tempFile->getPathname()) : null;
        $this->extractPluginZip($tempFile->getPathname(), storeType: $type);

        if ($type === self::PLUGIN) {
            \assert($metadata !== null);
            $this->pluginService->refreshPlugins($context, new NullIO());
            $this->eventDispatcher->dispatch(new PluginUploadedEvent($file->getClientOriginalName(), $context, $metadata['pluginName'], $metadata['pluginVersion']));
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
        // when `executeComposerCommands` is set to true `managedByComposer` will be true even for plugins installed via the admin
        // so we need to check the path as well and allow removal of plugins in `custom/plugins` folder
        if ($plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw PluginException::cannotDeleteManaged($plugin->getName());
        }

        $path = $this->projectDir . '/' . $plugin->getPath();
        $this->filesystem->remove($path);

        $this->pluginService->refreshPlugins($context, new NullIO());
    }

    /**
     * @return array{pluginName: string, pluginVersion: string|null}
     */
    private function readUploadMetadata(string $path): array
    {
        $archive = ZipUtils::openZip($path);
        try {
            $entry = $archive->statIndex(0);
            \assert($entry !== false);
            $directory = explode('/', (string) $entry['name'])[0];
            $composerJson = $archive->getFromName($directory . '/composer.json');
            $composer = \is_string($composerJson) ? json_decode($composerJson, true, flags: \JSON_THROW_ON_ERROR) : null;
            $extra = \is_array($composer) ? ($composer['extra'] ?? null) : null;
            $class = \is_array($extra) ? ($extra['shopware-plugin-class'] ?? null) : null;
            $version = \is_array($composer) ? ($composer['version'] ?? null) : null;

            return [
                'pluginName' => \is_string($class) && $class !== '' ? basename(str_replace('\\', '/', $class)) : $directory,
                'pluginVersion' => \is_string($version) ? $version : null,
            ];
        } finally {
            $archive->close();
        }
    }

    private function extractPlugin(string $fileName, bool $delete): void
    {
        $this->extensionExtractor->extract($fileName, $delete, self::PLUGIN);
        $this->cacheClearer->clearContainerCache();
    }

    private function extractApp(string $fileName, bool $delete): void
    {
        $this->extensionExtractor->extract($fileName, $delete, self::APP);
    }
}
