---
title: Introduce global template data for language and navigation
issue: NEXT-39744
author: Michael Telgmann
author_github: @mitelg
---

# Core
* Deprecated `\Shopware\Core\System\SalesChannel\Exception\ContextPermissionsLockedException`. Use `\Shopware\Core\System\SalesChannel\SalesChannelException::contextPermissionsLocked` instead
* Deprecated `\Shopware\Core\System\SalesChannel\Exception\ContextRulesLockedException`. Use `\Shopware\Core\System\SalesChannel\SalesChannelException::contextRulesLocked` instead
* Deprecated `\Shopware\Core\System\Tax\Exception\TaxNotFoundException`. Use `\Shopware\Core\System\SalesChannel\SalesChannelException::taxNotFound` instead
___

# Storefront
* Added new Twig function `sw_breadcrumb_full_by_id` to get the full breadcrumb for a category ID.
* Added `\Shopware\Storefront\Framework\Twig\NavigationInfo` to the global `shopware` Twig variable, to provide the ID of the main navigation and the current navigation path as ID list.
* Added `minSearchLength` to the global `shopware` Twig variable, which defines the minimum search term length.
* Added `showStagingBanner` to the global `shopware` Twig variable, which defines if the staging banner should be shown.
* Deprecated the global `showStagingBanner` Twig variable. Use `shopware.showStagingBanner` instead.
* Deprecated the usage of the `header` and `footer` properties of page Twig objects outside the dedicated header and footer templates. Use the following alternatives instead:
    * `context.currency` instead of `page.header.activeCurrency`
    * `shopware.navigation.id` instead of `page.header.navigation.active.id`
    * `shopware.navigation.pathIdList` instead of `page.header.navigation.active.path`
    * `context.saleschannel.languages.first` instead of `page.header.activeLanguage`
* Added new optional parameter `serviceMenu` of type `\Shopware\Core\Content\Category\CategoryCollection` to `\Shopware\Storefront\Pagelet\Footer\FooterPagelet`. It will be required in the next major version.
___

# Upgrade Information

## Deprecation of Twig variable
The global `showStagingBanner` Twig variable has been deprecated. Use `shopware.showStagingBanner` instead.

## New constructor parameter in FooterPagelet
The new optional parameter `serviceMenu` of type `\Shopware\Core\Content\Category\CategoryCollection` has been added to `\Shopware\Storefront\Pagelet\Footer\FooterPagelet`.
You can already add it to your implementation to prevent breaking changes, as it will be required in the next major version.
___

# Next Major Version Changes

## Removal of Twig variable
The global `showStagingBanner` Twig variable was removed. Use `shopware.showStagingBanner` instead.

## FooterPagelet changes
The former optional parameter `serviceMenu` of type `\Shopware\Core\Content\Category\CategoryCollection` in `\Shopware\Storefront\Pagelet\Footer\FooterPagelet` is now required.
Make sure to pass it to the constructor.
