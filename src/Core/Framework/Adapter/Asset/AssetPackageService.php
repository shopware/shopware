<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;

class AssetPackageService
{
    /**
     * @var Packages
     */
    private $packages;

    public function __construct(Packages $packages)
    {
        $this->packages = $packages;
    }

    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        $this->packages->addPackage(
            '@' . $bundleName,
            new PathPackage('/bundles/' . mb_strtolower($bundleName), new LastModifiedVersionStrategy($bundlePath))
        );
    }
}
