<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Asset package of a single bundle, registered as `@<BundleName>`.
 * The base URL is taken from the shared asset package on every call, because that URL can depend on the current request.
 *
 * @internal
 */
#[Package('framework')]
final class BundleAssetPackage extends UrlPackage
{
    public function __construct(
        private readonly PackageInterface $assetPackage,
        private readonly string $bundlePath,
        VersionStrategyInterface $versionStrategy,
    ) {
        // The parent validates the URL once, so an invalid asset URL still fails while the packages are created
        parent::__construct($assetPackage->getUrl($bundlePath), $versionStrategy);
    }

    public function getBaseUrl(string $path): string
    {
        return rtrim($this->assetPackage->getUrl($this->bundlePath), '/');
    }
}
