---
title: Disable recursive loading of SCSS in plugins
issue: NEXT-7365
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Core
* Removed parameter `$rootPath` from private method `getStyleFiles` in `Shopware\Core\Framework\Plugin\BundleConfigGenerator`
* Removed `Symfony\Component\Finder\Finder` usage in `Shopware\Core\Framework\Plugin\BundleConfigGenerator`
___
# Storefront
* Added new private method `getScssEntryFileInDir` in `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration` which is used to collect the SCSS entry
___
# Upgrade Information
## Changed the loading of storefront SCSS files in plugins

Previously all Storefront relevant SCSS files (`*.scss`) of a plugin have automatically been loaded and compiled by shopware when placed inside the directory `src/Resources/app/storefront/src/scss`.
Because all SCSS files have been loaded automatically it could have let to inconsistent results when dealing with custom SCSS variables in separate files for example.

This behaviour has been changed and now only a single entry file will be used by plugins which is the `YourPlugin/src/Resources/app/storefront/src/scss/base.scss`.

### Before

All the SCSS files in this example directory have been loaded automatically:

```
└── scss
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

### After

Now you need a `base.scss` and need to load all other files from there using the `@import` rule:

```
└── scss
    ├── base.scss <-- This is now mandatory and loads all other files
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

The `base.scss` for the previous example directory would look like this in order to load all SCSS properly:

```scss
// Content of the base.scss
@import 'variables';
@import 'header';
@import 'product-detail';
@import 'custom-component';
```
