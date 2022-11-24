<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @package core
 */
class AssetService
{
    private FilesystemOperator $filesystem;

    private KernelInterface $kernel;

    private CacheInvalidator $cacheInvalidator;

    private AbstractAppLoader $appLoader;

    private string $coreDir;

    private KernelPluginLoader $pluginLoader;

    private ParameterBagInterface $parameterBag;

    /**
     * @internal
     */
    public function __construct(
        FilesystemOperator $filesystem,
        KernelInterface $kernel,
        KernelPluginLoader $pluginLoader,
        CacheInvalidator $cacheInvalidator,
        AbstractAppLoader $appLoader,
        string $coreDir,
        ParameterBagInterface $parameterBag
    ) {
        $this->filesystem = $filesystem;
        $this->kernel = $kernel;
        $this->pluginLoader = $pluginLoader;
        $this->cacheInvalidator = $cacheInvalidator;
        $this->coreDir = $coreDir;
        $this->appLoader = $appLoader;
        $this->parameterBag = $parameterBag;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function copyAssetsFromBundle(string $bundleName): void
    {
        $bundle = $this->getBundle($bundleName);

        $this->copyAssets($bundle);

        if ($bundle instanceof Plugin) {
            foreach ($this->getAdditionalBundles($bundle) as $bundle) {
                $this->copyAssets($bundle);
            }
        }
    }

    public function copyAssets(BundleInterface $bundle): void
    {
        $originDir = $bundle->getPath() . '/Resources/public';
        if (!is_dir($originDir)) {
            return;
        }

        $this->removeAssets($bundle->getName());

        $targetDirectory = $this->getTargetDirectory($bundle->getName());
        $this->filesystem->deleteDirectory($targetDirectory);

        $this->copy($originDir, $targetDirectory);

        $this->cacheInvalidator->invalidate(['asset-metaData'], true);
    }

    public function copyAssetsFromApp(string $appName, string $appPath): void
    {
        $originDir = $this->appLoader->getAssetPathForAppPath($appPath);

        if (!is_dir($originDir)) {
            return;
        }

        $this->removeAssets($appName);

        $targetDirectory = $this->getTargetDirectory($appName);
        $this->filesystem->deleteDirectory($targetDirectory);

        $this->copy($originDir, $targetDirectory);

        $this->cacheInvalidator->invalidate(['asset-metaData'], true);
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
        } catch (PluginNotFoundException $e) {
            // plugin is already unloaded, we cannot find it. Ignore it
        }
    }

    public function copyRecoveryAssets(): void
    {
        $targetDirectory = 'recovery';

        // @codeCoverageIgnoreStart
        if (is_dir($this->coreDir . '/../Recovery/Resources/public')) {
            // platform installation
            $originDir = $this->coreDir . '/../Recovery/Resources/public';
        } elseif (is_dir($this->coreDir . '/../recovery/Resources/public')) {
            // composer installation over many repos
            $originDir = $this->coreDir . '/../recovery/Resources/public';
        } else {
            return;
        }
        // @codeCoverageIgnoreEnd

        $this->filesystem->deleteDirectory($targetDirectory);

        $this->copy($originDir, $targetDirectory);
    }

    public function removeAssets(string $name): void
    {
        $targetDirectory = $this->getTargetDirectory($name);

        $this->filesystem->deleteDirectory($targetDirectory);
    }

    private function getTargetDirectory(string $name): string
    {
        $assetDir = preg_replace('/bundle$/', '', mb_strtolower($name));

        return 'bundles/' . $assetDir;
    }

    private function copy(string $originDir, string $targetDir): void
    {
        $this->filesystem->createDirectory($targetDir);

        $files = Finder::create()
            ->ignoreDotFiles(false)
            ->files()
            ->in($originDir)
            ->getIterator();

        foreach ($files as $file) {
            $fs = fopen($file->getPathname(), 'rb');

            // @codeCoverageIgnoreStart
            if (!\is_resource($fs)) {
                throw new \RuntimeException('Could not open file ' . $file->getPathname());
            }
            // @codeCoverageIgnoreEnd

            $this->filesystem->writeStream($targetDir . '/' . $file->getRelativePathname(), $fs);
            fclose($fs);
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
            $bundle = $this->pluginLoader->getPluginInstances()->get($bundleName);
        }

        if ($bundle === null) {
            throw new PluginNotFoundException($bundleName);
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
}
