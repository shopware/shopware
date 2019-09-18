<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Asset;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

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

    public function addAssetPackage(string $bundleName): void
    {
        $this->packages->addPackage(
            '@' . $bundleName,
            new PathPackage('/bundles/' . mb_strtolower($bundleName), new EmptyVersionStrategy())
        );
    }
}
