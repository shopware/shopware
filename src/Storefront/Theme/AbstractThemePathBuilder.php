<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Feature;
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
     *
     * @deprecated tag:v6.6.0 - Method will be abstract in v6.6.0, so implement the method in your implementations
     */
    public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            sprintf(
                'Method "%s" will be abstract in v6.6.0, so you need to implement the method in "%s".',
                __METHOD__,
                static::class
            )
        );

        return $this->assemblePath($salesChannelId, $themeId);
    }

    /**
     * `saveSeed()` is called after the successful compilation of a theme.
     * I should save the seed so that it will be used for subsequent calls to `assemblePath()`.
     *
     * @deprecated tag:v6.6.0 - Method will be abstract in v6.6.0, so implement the method in your implementations
     */
    public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            sprintf(
                'Method "%s" will be abstract in v6.6.0, so you need to implement the method in "%s".',
                __METHOD__,
                static::class
            )
        );

        // empty for backwards compatibility
    }
}
