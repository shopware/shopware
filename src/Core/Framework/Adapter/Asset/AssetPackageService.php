<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

#[\Shopware\Core\Framework\Log\Package('core')]
class AssetPackageService
{
    /**
     * @deprecated tag:v6.6.0 - Will be removed, will be now automatically registered
     */
    public function addAssetPackage(string $bundleName, string $bundlePath): void
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, 'addAssetPackage', 'Will be automatically registered'));
    }

    /**
     * @param array<string, string> $bundleMap
     */
    public static function create(array $bundleMap, Package $package, VersionStrategyInterface $versionStrategy, mixed ...$args): Packages
    {
        $packages = new Packages(...$args);

        foreach ($bundleMap as $bundleName => $bundlePath) {
            /** @see AssetService::getTargetDirectory() */
            $targetPath = '/bundles/' . preg_replace('/bundle$/', '', mb_strtolower($bundleName));

            $path = $package->getUrl($targetPath);
            $packages->addPackage(
                '@' . $bundleName,
                new UrlPackage($path, new PrefixVersionStrategy($targetPath, $versionStrategy))
            );
        }

        return $packages;
    }
}
