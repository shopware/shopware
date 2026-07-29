<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\Visibility;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\AssetUploadEvent;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal with next major. Should then be moved to the `Shopware\Core\Framework\Adapter\Asset` namespace
 */
#[Package('framework')]
class AssetService
{
    private const EXTENSION_RESOURCES_DIRECTORY = 'Resources/public';
    private const ASSET_MANIFEST_FILENAME = 'asset-manifest.json';

    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $assetFilesystem,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly KernelInterface $kernel,
        private readonly KernelPluginLoader $pluginLoader,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly SourceResolver $sourceResolver,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     * @throws PluginNotFoundException
     * @throws UnableToCheckExistence
     * @throws UnableToCreateDirectory
     * @throws UnableToDeleteDirectory
     */
    public function copyAssetsFromBundle(string $bundleName, bool $force = false): void
    {
        $this->copyAssets($this->getBundle($bundleName), $force);
    }

    /**
     * @throws \JsonException
     * @throws UnableToDeleteDirectory
     * @throws UnableToCreateDirectory
     * @throws UnableToCheckExistence
     * @throws FilesystemException
     */
    public function copyAssets(BundleInterface $bundle, bool $force = false): void
    {
        if ($bundle instanceof Plugin) {
            foreach ($this->getAdditionalBundles($bundle) as $additionalBundle) {
                $this->copyAssets($additionalBundle, $force);
            }
        }

        $this->copyAssetsFromBundleOrApp(
            Path::join($bundle->getPath(), self::EXTENSION_RESOURCES_DIRECTORY),
            $bundle->getName(),
            $force,
        );
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     * @throws UnableToCreateDirectory
     * @throws UnableToDeleteDirectory
     */
    public function copyAssetsFromApp(string $appName, string $appPath, bool $force = false): void
    {
        $fs = $this->sourceResolver->filesystemForAppName($appName);

        if (!$fs->has(self::EXTENSION_RESOURCES_DIRECTORY)) {
            return;
        }

        $publicDirectory = $fs->path(self::EXTENSION_RESOURCES_DIRECTORY);

        $this->copyAssetsFromBundleOrApp(
            $publicDirectory,
            $appName,
            $force,
        );
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     * @throws UnableToDeleteDirectory
     */
    public function removeAssetsOfBundle(string $bundleName): void
    {
        $this->removeAssets($bundleName);

        $bundle = null;
        try {
            $bundle = $this->getBundle($bundleName);
        } catch (PluginNotFoundException) {
            // plugin is already unloaded, we cannot find it. Ignore it
        }

        if ($bundle instanceof Plugin) {
            foreach ($this->getAdditionalBundles($bundle) as $additionalBundle) {
                $this->removeAssets($additionalBundle->getName());
            }
        }
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     * @throws UnableToDeleteDirectory
     */
    public function removeAssets(string $name): void
    {
        $targetDirectory = $this->getTargetDirectory($name);

        $this->assetFilesystem->deleteDirectory($targetDirectory);

        $manifest = $this->getManifest();

        unset($manifest[mb_strtolower($name)]);
        $this->writeManifest($manifest);
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     * @throws UnableToCreateDirectory
     * @throws UnableToDeleteDirectory
     */
    private function copyAssetsFromBundleOrApp(
        string $originDirectory,
        string $bundleOrAppName,
        bool $force,
    ): void {
        if (!is_dir($originDirectory)) {
            return;
        }

        $bundleOrAppName = mb_strtolower($bundleOrAppName);

        $manifest = $this->getManifest();

        if ($force) {
            unset($manifest[$bundleOrAppName]);
        }

        $targetDirectory = $this->getTargetDirectory($bundleOrAppName);

        if ($manifest === [] || !isset($manifest[$bundleOrAppName])) {
            // if there is no manifest file or no entry for the current bundle, we need to remove all assets and start fresh
            $this->assetFilesystem->deleteDirectory($targetDirectory);
        }

        if (!$this->assetFilesystem->directoryExists($targetDirectory)) {
            $this->assetFilesystem->createDirectory($targetDirectory);
        }

        $remoteBundleManifest = $manifest[$bundleOrAppName] ?? [];
        $localBundleManifest = $this->buildBundleManifest(
            $this->getBundleFiles($originDirectory)
        );

        if ($remoteBundleManifest === $localBundleManifest) {
            return;
        }

        $this->sync($originDirectory, $targetDirectory, $localBundleManifest, $remoteBundleManifest);

        $manifest[$bundleOrAppName] = $localBundleManifest;
        $this->writeManifest($manifest);

        if (!EnvironmentHelper::getVariable('SHOPWARE_SKIP_ASSET_INSTALL_CACHE_INVALIDATION', false)) {
            $this->cacheInvalidator->invalidate(['asset-metaData'], true);
        }
    }

    /**
     * @return array<SplFileInfo>
     */
    private function getBundleFiles(string $directory): array
    {
        $files = Finder::create()
            ->ignoreDotFiles(false)
            ->files()
            ->in($directory)
            ->getIterator();

        return array_values(iterator_to_array($files, false));
    }

    /**
     * @param array<SplFileInfo> $files
     *
     * @return array<string, string>
     */
    private function buildBundleManifest(array $files): array
    {
        $localManifest = array_combine(
            array_map(static fn (SplFileInfo $file) => $file->getRelativePathname(), $files),
            array_map(static fn (SplFileInfo $file) => Hasher::hashFile($file->getPathname()), $files)
        );

        ksort($localManifest);

        return $localManifest;
    }

    /**
     * Adopted from symfony, as they also strip the bundle suffix:
     * https://github.com/symfony/symfony/blob/7.2/src/Symfony/Bundle/FrameworkBundle/Command/AssetsInstallCommand.php#L128
     */
    private function getTargetDirectory(string $name): string
    {
        $assetDir = (string) preg_replace('/bundle$/', '', mb_strtolower($name));

        return Path::join('bundles', $assetDir);
    }

    /**
     * Each manifest is a hashmap of file names and their content hash, eg:
     * [
     *     'file1' => 'a1b2c3',
     *     'file2' => 'a2b4c6',
     * ]
     *
     * @param array<string, string> $localManifest
     * @param array<string, string> $remoteManifest
     */
    private function sync(string $originDir, string $targetDirectory, array $localManifest, array $remoteManifest): void
    {
        // compare the file names and hashes: will return a list of files not present in remote as well
        // as files with changed hashes
        $uploads = array_keys(array_diff_assoc($localManifest, $remoteManifest));

        // diff the opposite way to find files which are present remotely, but not locally.
        // we use array_diff_key because we don't care about the hash, just the file names
        $filesToDelete = array_keys(array_diff_key($remoteManifest, $localManifest));

        $uploadEvent = $this->eventDispatcher->dispatch(new AssetUploadEvent(
            $uploads,
            $filesToDelete,
        ));

        $batches = [];
        foreach ($uploadEvent->filesToUpload as $file) {
            $batches[] = new CopyBatchInput(
                Path::join($originDir, $file),
                [Path::join($targetDirectory, $file)],
                $this->getAssetVisibility(),
            );
        }

        CopyBatch::copy($this->assetFilesystem, ...$batches);

        // Delete remote files, that are not present locally
        foreach ($uploadEvent->filesToDelete as $file) {
            $this->assetFilesystem->delete(Path::join($targetDirectory, $file));
        }
    }

    /**
     * @throws PluginNotFoundException
     */
    private function getBundle(string $bundleName): BundleInterface
    {
        try {
            $bundle = $this->kernel->getBundle($bundleName);
        } catch (\InvalidArgumentException) {
            $bundle = $this->pluginLoader->getPluginInstances()->get($bundleName);
        }

        if ($bundle === null) {
            throw PluginException::notFound($bundleName);
        }

        return $bundle;
    }

    /**
     * @return array<BundleInterface>
     */
    private function getAdditionalBundles(Plugin $bundle): array
    {
        $params = new AdditionalBundleParameters(
            $this->pluginLoader->getClassLoader(),
            $this->pluginLoader->getPluginInstances(),
            $this->parameterBag->all()
        );

        return $bundle->getAdditionalBundles($params);
    }

    /**
     * @throws \JsonException
     * @throws FilesystemException
     *
     * @return array<string, array<string, string>>
     */
    private function getManifest(): array
    {
        if ($this->areAssetsStoredLocally()) {
            return [];
        }

        $hashes = [];
        try {
            $hashes = json_decode($this->privateFilesystem->read(self::ASSET_MANIFEST_FILENAME), true, flags: \JSON_THROW_ON_ERROR);
        } catch (UnableToReadFile) {
        }

        return $hashes;
    }

    /**
     * Manifest file is saved in private file system and not in asset file system itself to ensure,
     * that no information about installed apps and plugins are exposed
     *
     * @param array<string, array<string, string>> $manifest
     *
     * @throws \JsonException
     * @throws FilesystemException
     */
    private function writeManifest(array $manifest): void
    {
        if ($this->areAssetsStoredLocally()) {
            return;
        }

        $this->privateFilesystem->write(
            self::ASSET_MANIFEST_FILENAME,
            json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * If the private file system is remotely, but the assets are stored locally, it could lead to problems.
     * Therefore, we do not save a manifest file at all.
     */
    private function areAssetsStoredLocally(): bool
    {
        return $this->parameterBag->get('shopware.filesystem.asset.type') === 'local';
    }

    private function getAssetVisibility(): string
    {
        $legacyVisibility = null;

        if (!Feature::isActive('v6.8.0.0')) {
            // Remove the whole $legacyVisibility block when removing the v6.8.0.0 feature flag.
            try {
                $assetConfig = $this->parameterBag->get('shopware.filesystem.asset.config');
            } catch (ParameterNotFoundException) {
                $assetConfig = null;
            }

            $legacyVisibility = \is_array($assetConfig) ? $assetConfig['visibility'] ?? null : null;
        }

        try {
            return (string) ($legacyVisibility ?? $this->parameterBag->get('shopware.filesystem.asset.visibility') ?? Visibility::PUBLIC);
        } catch (ParameterNotFoundException) {
            return Visibility::PUBLIC;
        }
    }
}
