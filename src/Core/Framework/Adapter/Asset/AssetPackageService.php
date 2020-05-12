<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;

class AssetPackageService
{
    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var string
     */
    private $cdnUrl;

    public function __construct(Packages $packages, ?string $cdnUrl)
    {
        $this->packages = $packages;
        $this->cdnUrl = $cdnUrl;
    }

    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        if ($this->cdnUrl) {
            $package = new UrlPackage($this->cdnUrl . '/bundles/' . mb_strtolower($bundleName), new LastModifiedVersionStrategy($bundlePath));
        } else {
            $package = new PathPackage('/bundles/' . mb_strtolower($bundleName), new LastModifiedVersionStrategy($bundlePath));
        }
        $this->packages->addPackage(
            '@' . $bundleName,
            $package
        );
    }
}
