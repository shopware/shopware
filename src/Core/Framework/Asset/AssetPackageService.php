<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Asset;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;

class AssetPackageService
{
    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var string
     */
    private $basePath = '';

    public function __construct(Packages $packages, string $basePath)
    {
        $this->packages = $packages;
        $this->basePath = $basePath;
    }

    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        $this->packages->addPackage(
            '@' . $bundleName,
            new PathPackage(rtrim($this->basePath, '/') . '/bundles/' . mb_strtolower($bundleName), new LastModifiedVersionStrategy($bundlePath))
        );
    }
}
