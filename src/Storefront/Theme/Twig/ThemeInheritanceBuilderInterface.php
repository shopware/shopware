<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
interface ThemeInheritanceBuilderInterface
{
    /**
     * Themes can define the inheritance order for templates. For example, you can define a theme that first loads the templates from your own theme, then from the plugins and finally from the Shopware Storefront theme.
     * This Inheritance is built here correctly. The corresponding configuration takes place in the Resources\theme.json. This can look like the following:
     *
     * ```
     *  {
     *      "views": [
     *          "@Storefront,
     *          "@SwagPayPal"
     *          "@Plugins"
     *          "@MyNewTheme"
     *      ],
     *  }
     * ```
     *
     * - @Storefront stands here for the Shopware Storefront theme
     * - @SwagPayPal explicitly defines the order in which the PayPal plugin should be considered
     * - @Plugins is a wildcard for all plugins that are not explicitly specified.
     * - @MyNewTheme stands for your own theme, which should be inherited from Storefront.
     */
    public function build(array $bundles, array $themes): array;
}
