<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

interface AssetServiceInterface
{
    /**
     * Copies the assets from a given bundle to the public directory
     */
    public function copyAssetsFromBundle(string $bundleName): void;

    /**
     * Removes the assets of a given bundle from the public directory
     */
    public function removeAssetsOfBundle(string $bundleName): void;
}
