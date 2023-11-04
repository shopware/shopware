<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

#[\Shopware\Core\Framework\Log\Package('core')]
class AssetPackageService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Packages $packages,
        private readonly Package $package,
        private readonly VersionStrategyInterface $versionStrategy
    ) {
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
