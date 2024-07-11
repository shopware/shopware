<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('core')]
class AssetService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly KernelInterface $kernel,
        private readonly KernelPluginLoader $pluginLoader,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly SourceResolver $sourceResolver,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * @throws PluginNotFoundException
     */
    public function copyAssetsFromBundle(string $bundleName, bool $force = false): void
    {
        $bundle = $this->getBundle($bundleName);

        $this->copyAssets($bundle, $force);

        if ($bundle instanceof Plugin) {
            foreach ($this->getAdditionalBundles($bundle) as $bundle) {
                $this->copyAssets($bundle, $force);
            }
        }
    }

    public function copyAssets(BundleInterface $bundle, bool $force = false): void
    {
        $this->copyAssetsFromBundleOrApp(
            $bundle->getPath() . '/Resources/public',
            $bundle->getName(),
            $force
        );
    }

    public function copyAssetsFromApp(string $appName, string $appPath, bool $force = false): void
    {
        $fs = $this->sourceResolver->filesystemForAppName($appName);

        if (!$fs->has('Resources/public')) {
            return;
        }

        $publicDirectory = $fs->path('Resources/public');

        $this->copyAssetsFromBundleOrApp(
            $publicDirectory,
            $appName,
            $force
        );
    }

    public function removeAssetsOfBundle(string $bundleName): void
    {
        $this->removeAssets($bundleName);

        try {
            $bundle = $this->getBundle($bundleName);

            if ($bundle instanceof Plugin) {
                foreach ($this->getAdditionalBundles($bundle) as $bundle) {
                    $this->removeAssets($bundle->getName());
                }
            }
        } catch (PluginNotFoundException) {
            // plugin is already unloaded, we cannot find it. Ignore it
        }
    }

    public function removeAssets(string $name): void
    {
        $targetDirectory = $this->getTargetDirectory($name);

        $this->filesystem->deleteDirectory($targetDirectory);

        $manifest = $this->getManifest();

        unset($manifest[mb_strtolower($name)]);
        $this->writeManifest($manifest);
    }

    private function copyAssetsFromBundleOrApp(string $originDirectory, string $bundleOrAppName, bool $force): void
    {
        $bundleOrAppName = mb_strtolower($bundleOrAppName);

        if (!is_dir($originDirectory)) {
            return;
        }

        $manifest = $this->getManifest();

        if ($force) {
            unset($manifest[$bundleOrAppName]);
        }

        $targetDirectory = $this->getTargetDirectory($bundleOrAppName);

        if (empty($manifest) || !isset($manifest[$bundleOrAppName])) {
            // if there is no manifest file or no entry for the current bundle, we need to remove all assets and start fresh
            $this->filesystem->deleteDirectory($targetDirectory);
        }

        if (!$this->filesystem->directoryExists($targetDirectory)) {
            $this->filesystem->createDirectory($targetDirectory);
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

        $this->cacheInvalidator->invalidate(['asset-metaData'], true);
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

        return array_values(iterator_to_array($files));
    }

    /**
     * @param array<SplFileInfo> $files
     *
     * @return array<string, string>
     */
    private function buildBundleManifest(array $files): array
    {
        $localManifest = array_combine(
            array_map(fn (SplFileInfo $file) => $file->getRelativePathname(), $files),
            array_map(fn (SplFileInfo $file) => (string) hash_file('sha256', $file->getPathname()), $files)
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
        $assetDir = preg_replace('/bundle$/', '', mb_strtolower($name));

        return 'bundles/' . $assetDir;
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

        // diff the opposite way to find files which are present remote, but not locally.
        // we use array_diff_key because we don't care about the hash, just the file names
        $removes = array_keys(array_diff_key($remoteManifest, $localManifest));

        foreach ($removes as $file) {
            $this->filesystem->delete($targetDirectory . '/' . $file);
        }

        $batches = [];

        foreach ($uploads as $file) {
            $batches[] = new CopyBatchInput(
                $originDir . '/' . $file,
                [$targetDirectory . '/' . $file]
            );
        }

        CopyBatch::copy($this->filesystem, ...$batches);
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
     * @return array<string, array<string, string>>
     */
    private function getManifest(): array
    {
        if ($this->areAssetsStoredLocally()) {
            return [];
        }

        $hashes = [];
        if ($this->privateFilesystem->fileExists('asset-manifest.json')) {
            $hashes = json_decode(
                $this->privateFilesystem->read('asset-manifest.json'),
                true,
                \JSON_THROW_ON_ERROR
            );
        }

        return $hashes;
    }

    /**
     * @param array<string, array<string, string>> $manifest
     */
    private function writeManifest(array $manifest): void
    {
        if ($this->areAssetsStoredLocally()) {
            return;
        }

        $this->privateFilesystem->write(
            'asset-manifest.json',
            json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
        );
    }

    private function areAssetsStoredLocally(): bool
    {
        return $this->parameterBag->get('shopware.filesystem.asset.type') === 'local';
    }
}
