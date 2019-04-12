<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

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
    protected $filesystem;

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

    public function copyAssetsFromBundle(BundleInterface $bundle): void
    {
        $originDir = $bundle->getPath() . '/Resources/public';
        if (!is_dir($originDir)) {
            return;
        }

        $targetDirectory = $this->getTargetDirectory($bundle);

        $this->filesystem->remove($targetDirectory);

        $this->copy($originDir, $targetDirectory);
    }

    public function removeAssetsOfBundle(BundleInterface $bundle): void
    {
        $targetDirectory = $this->getTargetDirectory($bundle);

        $this->filesystem->remove($targetDirectory);
    }

    protected function getTargetDirectory(BundleInterface $bundle): string
    {
        $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));

        return 'public/bundles/' . $assetDir;
    }

    private function copy(string $originDir, string $targetDir): void
    {
        $this->filesystem->mkdir($targetDir, 0777);
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));
    }
}
