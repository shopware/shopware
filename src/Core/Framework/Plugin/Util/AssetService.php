<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
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
        private readonly KernelInterface $kernel,
        private readonly KernelPluginLoader $pluginLoader,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly AbstractAppLoader $appLoader,
        private readonly ParameterBagInterface $parameterBag
    ) {
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

    /**
     * @decrecated tag:v6.6.0 - Will be removed without replacement
     */
    public function copyRecoveryAssets(): void
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0'));
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
        } catch (PluginNotFoundException) {
            // plugin is already unloaded, we cannot find it. Ignore it
        }
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

            // The Google Cloud Storage filesystem closes the stream even though it should not. To prevent a fatal
            // error, we therefore need to check whether the stream has been closed yet.
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
        } catch (\InvalidArgumentException) {
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
