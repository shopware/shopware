<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Asset;

use Shopware\Core\Framework\Bundle;
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

    public function addAssetPackage(Bundle $bundle): void
    {
        $this->packages->addPackage(
            '@' . $bundle->getName(),
            new PathPackage('/bundles/' . strtolower($bundle->getName()), new EmptyVersionStrategy())
        );
    }
}
