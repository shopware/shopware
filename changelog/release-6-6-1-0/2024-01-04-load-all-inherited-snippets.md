---
title: Load all inherited snippets
issue: NEXT-24159
---

# Storefront
* Changed `Shopware\Core\System\Snippet\SnippetService` to load all inherited snippets even from level 2 and above inheritances.
* Changed argument `$salesChannelThemeLoader` to `DatabseSalesChannelThemeLoader` in `Shopware\Core\System\Snippet\SnippetService`
* Changed `Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder` to use new `DatabaseSalsChannelThemeLoader`.
* Changed argument `$salesChannelThemeLoader` to `DatabaseSalesChannelThemeLoader` from `Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder`
* Added new abstract class `Shopware\Storefront\Theme\AbstractSalesChannelThemeLoader`
* Added `Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader` as a cachable variant of `Shopware\Storefront\Theme\SalesChannelThemeLoader`
* Deprecated `\Shopware\Storefront\Theme\SalesChannelThemeLoader`, use `\Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader` instead.
