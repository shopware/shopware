<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class AssetPackageService
{
    private Packages $packages;

    private Package $package;

    private VersionStrategyInterface $versionStrategy;

    public function __construct(Packages $packages, Package $package, VersionStrategyInterface $versionStrategy)
    {
        $this->packages = $packages;
        $this->package = $package;
        $this->versionStrategy = $versionStrategy;
    }

    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        $path = $this->package->getUrl('/bundles/' . mb_strtolower($bundleName));
        $this->packages->addPackage(
            '@' . $bundleName,
            new UrlPackage($path, new PrefixVersionStrategy('/bundles/' . mb_strtolower($bundleName), $this->versionStrategy))
        );
    }
}
