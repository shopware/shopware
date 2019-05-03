<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class AssetService
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    public function __construct(
        Filesystem $filesystem,
        KernelInterface $kernel,
        KernelPluginCollection $pluginCollection
    ) {
        $this->filesystem = $filesystem;
        $this->kernel = $kernel;
        $this->pluginCollection = $pluginCollection;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function copyAssetsFromBundle(string $bundleName, Context $shopwareContext): void
    {
        $bundle = $this->getBundle($bundleName);

        $originDir = $bundle->getPath() . '/Resources/public';
        if (!is_dir($originDir)) {
            return;
        }

        $targetDirectory = $this->getTargetDirectory($bundle, $shopwareContext);
        $this->filesystem->remove($targetDirectory);

        $this->copy($originDir, $targetDirectory);
    }

    /**
     * @throws PluginNotFoundException
     */
    public function removeAssetsOfBundle(string $bundleName, Context $shopwareContext): void
    {
        $bundle = $this->getBundle($bundleName);

        $targetDirectory = $this->getTargetDirectory($bundle, $shopwareContext);

        $this->filesystem->remove($targetDirectory);
    }

    private function getTargetDirectory(BundleInterface $bundle, Context $shopwareContext): string
    {
        $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));

        if ($shopwareContext->getScope() === Context::USER_SCOPE) {
            return 'bundles/' . $assetDir;
        }

        return 'public/bundles/' . $assetDir;
    }

    private function copy(string $originDir, string $targetDir): void
    {
        $this->filesystem->mkdir($targetDir, 0777);
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));
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
