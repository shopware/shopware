---
title:              New CMS components for 3D content       # Required
issue:              NEXT-12345                              # Required
flag:               FEATURE_NEXT_12345                      # Required, when feature is behind feature flag
author:             Philipp Schuch                          # Optional for shopware employees, Required for external developers
author_email:       p.schuch@shopware.com                   # Optional for shopware employees, Required for external developers
author_github:      @Phil23                                 # Optional
---
# Core
*  Added new method `getCategories` in `src/Core/Content/ProductStream/ProductStreamEntity.php`
*  Added new method `setCategories` in `src/Core/Content/ProductStream/ProductStreamEntity.php`
*  Deprecated the constructor of `Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage`. Use `CheckoutConfirmPage::createFrom` instead.
*  Removed required flag of `customer_id`
___
# API
*  Added association `product_stream.categories`, available with api/v3
*  Added entity `product_stream_category`, available with api/v3
___
# Administration
*  Added new computed prop `allowInlineEdit` to `sw-property-option-list/index.js`
*  Added new computed prop `tooltipAdd` to `sw-property-option-list/index.js`
*  Added new computed prop `disableAddButton` to `sw-property-option-list/index.js`
*  Deprecated `LanguageStore`
*  Removed deprecated component `sw-property-option-select/index.js`
___
# Storefront
*  Added new plugin class `clear-input.plugin.js`
*  Deprecated `window.accessKey` and `window.contextToken`, the variables contain now an empty string
*  Removed `/widgets/search/{search}` route
___
# Upgrade Information

## Asset System Refactoring
### Flysystem adapters
With 6.3 we have refactored the url handling of including resources like images, js, css etc. We have also created three new adapters: `asset` (plugin public files), `theme` (theme resources) and `sitemap`.
For comparability reason they inherit from the `public` filesytem. So after the update all new filesystem are using the config from public filesystem.

All file system configuration have now an `url` config option, this url will be used for url generation to the files.

### Usage of the Symfony asset
To unify the URL generation, we create a Symfony asset for each public filesystem adapter. This will build the correct URL with a version cache busting.
These assets are prefixed in dependency injection with `shopware.asset.{ADAPTER_NAME}`:  
*  `shopware.asset.public`
*  `shopware.asset.theme`
*  `shopware.asset.asset`

Example in PHP:
```
// This is an example. Please use dependency injection
$publicAsset = $container->get('shopware.asset.public');
```

```
// Get the full url to the image
$imageUrl = $publicAsset->getUrl('folder/image.png');
```

Example in Twig:
```
{{ asset('folder/image.png', 'public') }
```

Example in SCSS
```
body {
  background: url("#{$sw-asset-theme-url}/bundles/storefront/assets/img/some-image.webp");
}
```

To access in scss the asset url, you can use the variable `$sw-asset-theme-url`.
