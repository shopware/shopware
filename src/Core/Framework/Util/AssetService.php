<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use InvalidArgumentException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class AssetService implements AssetServiceInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Filesystem $filesystem, Kernel $kernel)
    {
        $this->filesystem = $filesystem;
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotFoundException
     */
    public function copyAssetsFromBundle(string $bundleName): void
    {
        $bundle = $this->getBundle($bundleName);

        $originDir = $bundle->getPath() . '/Resources/public';
        if (!is_dir($originDir)) {
            return;
        }

        $targetDirectory = $this->getTargetDirectory($bundle);

        $this->filesystem->remove($targetDirectory);

        $this->copy($originDir, $targetDirectory);
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotFoundException
     */
    public function removeAssetsOfBundle(string $bundleName): void
    {
        $bundle = $this->getBundle($bundleName);

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

    /**
     * @throws PluginNotFoundException
     */
    private function getBundle(string $bundleName): BundleInterface
    {
        try {
            $bundle = $this->kernel->getBundle($bundleName);
        } catch (InvalidArgumentException $e) {
            $bundle = $this->kernel::getPlugins()->get($bundleName);
        }

        if ($bundle === null) {
            throw new PluginNotFoundException($bundleName);
        }

        return $bundle;
    }
}
