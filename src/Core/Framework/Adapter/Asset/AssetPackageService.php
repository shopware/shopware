<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;

class AssetPackageService
{
    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var Package
     */
    private $package;

    public function __construct(Packages $packages, Package $package)
    {
        $this->packages = $packages;
        $this->package = $package;
    }

    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        $path = $this->package->getUrl('/bundles/' . mb_strtolower($bundleName));
        $this->packages->addPackage(
            '@' . $bundleName,
            new UrlPackage($path, new LastModifiedVersionStrategy($bundlePath))
        );
    }
}
