<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

#[Package('core')]
class AssetPackageService
{
    /**
     * @param array<string, string> $bundleMap
     */
    public static function create(array $bundleMap, AssetPackage $package, VersionStrategyInterface $versionStrategy, mixed ...$args): Packages
    {
        $packages = new Packages(...$args);

        if (!EnvironmentHelper::hasVariable('APP_URL')) {
            return $packages;
        }

        foreach ($bundleMap as $bundleName => $bundlePath) {
            /** @see AssetService::getTargetDirectory() */
            $targetPath = '/bundles/' . preg_replace('/bundle$/', '', mb_strtolower($bundleName));

            $path = $package->getUrl($targetPath);

            try {
                $bundlePackage = new UrlPackage($path, new PrefixVersionStrategy($targetPath, $versionStrategy));
            } catch (InvalidArgumentException $exception) {
                throw AdapterException::invalidAssetUrl($exception);
            }

            $packages->addPackage('@' . $bundleName, $bundlePackage);
        }

        return $packages;
    }
}
