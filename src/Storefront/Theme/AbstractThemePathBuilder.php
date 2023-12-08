<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;

/**
 * The AbstractThemePathBuilder handles access to the theme file paths
 * with a seeding mechanism to allow switching the concrete path for the currently active theme.
 */
#[Package('storefront')]
abstract class AbstractThemePathBuilder
{
    abstract public function getDecorated(): AbstractThemePathBuilder;

    /**
     * `assemblePath()` should return the path to the theme files for the given sales channel and theme.
     * It should also use the value of the lastly saved seed.
     *
     * Note that this method is called on every request, therefore the implementation needs to be really fast.
     */
    abstract public function assemblePath(string $salesChannelId, string $themeId): string;

    /**
     * `generateNewPath()` should work in the same way as `assemblePath()`, but it should use the given seed instead of the lastly saved one.
     * This method is used before the theme is recompiled to get a new location for the result of that compilation.
     */
    abstract public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string;

    /**
     * `saveSeed()` is called after the successful compilation of a theme.
     * I should save the seed so that it will be used for subsequent calls to `assemblePath()`.
     */
    abstract public function saveSeed(string $salesChannelId, string $themeId, string $seed): void;
}
