<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class AssetService
{
    private FilesystemInterface $filesystem;

    private KernelInterface $kernel;

    private KernelPluginCollection $pluginCollection;

    private CacheInvalidator $cacheInvalidator;

    private AbstractAppLoader $appLoader;

    private string $coreDir;

    public function __construct(
        FilesystemInterface $filesystem,
        KernelInterface $kernel,
        KernelPluginCollection $pluginCollection,
        CacheInvalidator $cacheInvalidator,
        AbstractAppLoader $appLoader,
        string $coreDir
    ) {
        $this->filesystem = $filesystem;
        $this->kernel = $kernel;
        $this->pluginCollection = $pluginCollection;
        $this->cacheInvalidator = $cacheInvalidator;
        $this->coreDir = $coreDir;
        $this->appLoader = $appLoader;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function copyAssetsFromBundle(string $bundleName): void
    {
        $bundle = $this->getBundle($bundleName);

        $originDir = $bundle->getPath() . '/Resources/public';
        if (!is_dir($originDir)) {
            return;
        }

        $targetDirectory = $this->getTargetDirectory($bundle->getName());
        $this->filesystem->deleteDir($targetDirectory);

        $this->copy($originDir, $targetDirectory);

        $this->cacheInvalidator->invalidate(['asset-metaData'], true);
    }

    public function copyAssetsFromApp(string $appName, string $appPath): void
    {
        $originDir = $this->appLoader->getAssetPathForAppPath($appPath);

        if (!is_dir($originDir)) {
            return;
        }

        $targetDirectory = $this->getTargetDirectory($appName);
        $this->filesystem->deleteDir($targetDirectory);

        $this->copy($originDir, $targetDirectory);

        $this->cacheInvalidator->invalidate(['asset-metaData'], true);
    }

    public function removeAssetsOfBundle(string $bundleName): void
    {
        $targetDirectory = $this->getTargetDirectory($bundleName);

        $this->filesystem->deleteDir($targetDirectory);
    }

    public function copyRecoveryAssets(): void
    {
        $targetDirectory = 'recovery';

        if (is_dir($this->coreDir . '/../Recovery/Resources/public')) {
            // platform installation
            $originDir = $this->coreDir . '/../Recovery/Resources/public';
        } elseif (is_dir($this->coreDir . '/../recovery/Resources/public')) {
            // composer installation over many repos
            $originDir = $this->coreDir . '/../recovery/Resources/public';
        } else {
            return;
        }

        $this->filesystem->deleteDir($targetDirectory);

        $this->copy($originDir, $targetDirectory);
    }

    private function getTargetDirectory(string $name): string
    {
        $assetDir = preg_replace('/bundle$/', '', mb_strtolower($name));

        return 'bundles/' . $assetDir;
    }

    private function copy(string $originDir, string $targetDir): void
    {
        $this->filesystem->createDir($targetDir);

        $files = Finder::create()
            ->ignoreDotFiles(false)
            ->files()
            ->in($originDir)
            ->getIterator();

        foreach ($files as $file) {
            $fs = fopen($file->getPathname(), 'rb');
            $this->filesystem->putStream($targetDir . '/' . $file->getRelativePathname(), $fs);
            if (\is_resource($fs)) {
                fclose($fs);
            }
        }
    }

    /**
     * @throws PluginNotFoundException
     */
    private function getBundle(string $bundleName): BundleInterface
    {
        try {
            $bundle = $this->kernel->getBundle($bundleName);
        } catch (\InvalidArgumentException $e) {
            $bundle = $this->pluginCollection->get($bundleName);
        }

        if ($bundle === null) {
            throw new PluginNotFoundException($bundleName);
        }

        return $bundle;
    }
}
