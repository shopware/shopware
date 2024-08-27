---
title: Introduce app filesystem abstraction
issue: NEXT-36382
author: Aydin Hassan
author_email: a.hassan@shopware.com
author_github: Aydin Hassan
---
# Core
* Deprecated `\Shopware\Core\Framework\App\Exception\AppXmlParsingException::__construct`, use static methods instead
* Added (internal) `\Shopware\Core\Framework\App\Source\SourceResolver` for accessing a scoped app filesystem
* Added (internal) `\Shopware\Core\Framework\App\Source\Source` interface to handle accessing the different types of app sources
* Added (internal) utilities for validating and extracting apps `\Shopware\Core\Framework\App\AppArchiveValidator` & `\Shopware\Core\Framework\App\AppExtractor`
* Added (internal) filesystem abstraction for scoped access `\Shopware\Core\Framework\Util\Filesystem`
___
# Storefront
* Deprecated `\Shopware\Storefront\Theme\ThemeFileImporterInterface` & `\Shopware\Storefront\Theme\ThemeFileImporter` they will be removed in v6.7.0
* Deprecated `getBasePath` & `setBasePath` methods and `basePath` property on `StorefrontPluginConfiguration` they will be removed in  v6.7.0. Paths are now stored relative to the app/plugin/bundle.
* Added (internal) `\Shopware\Storefront\Theme\ThemeFilesystemResolver` for accessing a scoped filesystem for an instance of `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration`
___
# Next Major Version Changes
## ThemeFileImporterInterface & ThemeFileImporter Removal
Both `\Shopware\Storefront\Theme\ThemeFileImporterInterface` & `\Shopware\Storefront\Theme\ThemeFileImporter` will be removed without replacement. These classes are already not used as of v6.7.0 and therefore this extension point is removed with no planned replacement.

`getBasePath` & `setBasePath` methods and `basePath` property on `StorefrontPluginConfiguration` are removed. If you need to get the absolute path you should ask for a filesystem instance via `\Shopware\Storefront\Theme\ThemeFilesystemResolver::getFilesystemForStorefrontConfig()` passing in the config object. 
This filesystem instance can read files via a relative path and also return the absolute path of a file. Eg:

```php
$fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($storefrontPluginConfig);
foreach($storefrontPluginConfig->getAssetPaths() as $relativePath) {
    $absolutePath = $fs->path('Resources', $relativePath);
}
```
