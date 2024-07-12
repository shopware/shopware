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
* Deprecated `\Shopware\Storefront\Theme\ThemeFileImporterInterface` & `\Shopware\Storefront\Theme\ThemeFileImporter` they will be removed in v6.8.0
* Added (internal) `\Shopware\Storefront\Theme\ThemeFilesystemResolver` for accessing a scoped filesystem for an instance of `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration`
___
# Next Major Version Changes
## ThemeFileImporterInterface & ThemeFileImporter Removal
Both `\Shopware\Storefront\Theme\ThemeFileImporterInterface` & `\Shopware\Storefront\Theme\ThemeFileImporter` will be removed without replacement. These classes are already not used as of v6.7.0 and therefore this extension point is removed with no planned replacement.
