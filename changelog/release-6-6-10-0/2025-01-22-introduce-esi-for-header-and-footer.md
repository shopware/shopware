---
title: Introduce ESI for header and footer
issue: NEXT-31674
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Added new resolver `\Shopware\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver` to enrich the `category-navigation` CMS element with navigation data.

___
# Storefront
* Added new route `\header` which returns the rendered header for ESI.
* Added new route `\footer` which returns the rendered footer for ESI.
* Added new template `src/Storefront/Resources/views/storefront/layout/header.html.twig` as new starting point for the header.
* Added new template `src/Storefront/Resources/views/storefront/layout/footer.html.twig` as new starting point for the footer.
* Added new template `src/Storefront/Resources/views/storefront/layout/navigation/active-styling.html.twig` to provide styling for the active navigation elements.
* Deprecated the properties `header` and `footer` and their getter and setter Methods in `\Shopware\Storefront\Framework\Twig\ErrorTemplateStruct`.
* Deprecated the loading of header, footer, payment methods and shipping methods in `\Shopware\Storefront\Page\GenericPageLoader`.
* Deprecated the properties `header`, `footer`, `salesChannelShippingMethods` and `salesChannelPaymentMethods` and their getter and setter Methods in `\Shopware\Storefront\Page\Page`.
* Deprecated the property `serviceMenu` and its getter and setter Methods in `\Shopware\Storefront\Pagelet\Header\HeaderPagelet`.
* Deprecated the `navigationId` request parameter in `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader::load`.
* Deprecated the `setNavigation` method in `\Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPagelet`.
* Deprecated option `tiggerEvent` in `OffcanvasMenuPlugin` JavaScript plugin. Use `triggerEvent` instead.
* Deprecated the following blocks in `src/Storefront/Resources/views/storefront/base.html.twig`. They will move to `src/Storefront/Resources/views/storefront/layout/header.html.twig`.
  * `base_header`
  * `base_header_inner`
  * `base_navigation`
  * `base_navigation_inner`
  * `base_offcanvas_navigation`
  * `base_offcanvas_navigation_inner`
* Deprecated the following blocks in `src/Storefront/Resources/views/storefront/base.html.twig`. They will move to `src/Storefront/Resources/views/storefront/layout/footer.html.twig`.
  * `base_footer`
  * `base_footer_inner`
* Deprecated the template variable `page` in following templates. Provide `header` or `footer` directly.
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/currency-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/top-bar.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`
* Deprecated the template variables `activeId` and `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/categories.html.twig`.
* Deprecated the template variable `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`.
* Deprecated parameter `activeResult` of `src/Storefront/Resources/views/storefront/layout/sidebar/category-navigation.html.twig` as it is not needed anymore.

___
# Upgrade Information
## Introduction of ESI for header and footer
With the next major version the header and footer will be loaded via ESI.
Due to this change many things were deprecated and will be removed with the next major version, as they are not needed anymore.
See the following chapter for a detailed list of deprecations.

### Deprecations
* The properties `header` and `footer` and their getter and setter Methods in `\Shopware\Storefront\Framework\Twig\ErrorTemplateStruct` are deprecated and will be removed with the next major version.
* The loading of header, footer, payment methods and shipping methods in `\Shopware\Storefront\Page\GenericPageLoader` is deprecated and will be removed with the next major version.
Extend `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader` or `\Shopware\Storefront\Pagelet\Footer\FooterPageletLoader` instead.
* The properties `header`, `footer`, `salesChannelShippingMethods` and `salesChannelPaymentMethods` and their getter and setter Methods in `\Shopware\Storefront\Page\Page` are deprecated and will be removed with the next major version.
Extend `\Shopware\Storefront\Pagelet\Header\HeaderPagelet` or `\Shopware\Storefront\Pagelet\Footer\FooterPagelet` instead.
* The property `serviceMenu` and its getter and setter Methods in `\Shopware\Storefront\Pagelet\Header\HeaderPagelet` are deprecated and will be removed with the next major version.
Extend it via the `\Shopware\Storefront\Pagelet\Footer\FooterPagelet` instead.
* The `navigationId` request parameter in `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader::load` is deprecated and will be removed with the next major version as it is not needed anymore.
* The `setNavigation` method in `\Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPagelet` is deprecated and will be removed with the next major version as it is unused.
* The option `tiggerEvent` in `OffcanvasMenuPlugin` JavaScript plugin is deprecated and will be removed with the next major version. Use `triggerEvent` instead.
* The following blocks will be moved from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/header.html.twig` in the next major version.
  * `base_header`
  * `base_header_inner`
  * `base_navigation`
  * `base_navigation_inner`
  * `base_offcanvas_navigation`
  * `base_offcanvas_navigation_inner`
* The following blocks will be moved from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/footer.html.twig` in the next major version.
  * `base_footer`
  * `base_footer_inner`
* The template variable `page` in following templates is deprecated and will be removed in the next major version. Provide `header` or `footer` directly.
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/currency-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/top-bar.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`
* The template variables `activeId` and `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/categories.html.twig` are deprecated and will be removed in the next major version.
* The template variable `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig` is deprecated and will be removed in the next major version.
* The parameter `activeResult` of `src/Storefront/Resources/views/storefront/layout/sidebar/category-navigation.html.twig` is deprecated and will be removed in the next major version.

___
# Next Major Version Changes
## Introduction of ESI for header and footer
The header and footer are now loaded via ESI.
This allows to cache the header and footer separately from the rest of the page.
Two new routes `\header` and `\footer` were added to receive the rendered header and footer.
The rendered header and footer are included into the page with the Twig function `render_esi`, which calls the previously mentioned routes.
Two new templates `src/Storefront/Resources/views/storefront/layout/header.html.twig` and `src/Storefront/Resources/views/storefront/layout/footer.html.twig` were introduced as new entry points for the header and footer.
Make sure to adjust your template extensions to be compatible with the new structure.
The block names are still the same, so it just should be necessary to extend from the new templates.

### Removals
* The properties `header` and `footer` and their getter and setter Methods in `\Shopware\Storefront\Framework\Twig\ErrorTemplateStruct` were removed.
* The loading of header, footer, payment methods and shipping methods in `\Shopware\Storefront\Page\GenericPageLoader` is removed.
  Extend `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader` or `\Shopware\Storefront\Pagelet\Footer\FooterPageletLoader` instead.
* The properties `header`, `footer`, `salesChannelShippingMethods` and `salesChannelPaymentMethods` and their getter and setter Methods in `\Shopware\Storefront\Page\Page` were removed.
  Extend `\Shopware\Storefront\Pagelet\Header\HeaderPagelet` or `\Shopware\Storefront\Pagelet\Footer\FooterPagelet` instead.
* The property `serviceMenu` and its getter and setter Methods in `\Shopware\Storefront\Pagelet\Header\HeaderPagelet` were removed.
  Extend it via the `\Shopware\Storefront\Pagelet\Footer\FooterPagelet` instead.
* The `navigationId` request parameter in `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader::load` was removed.
* The `setNavigation` method in `\Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPagelet` was removed.
* The option `tiggerEvent` in `OffcanvasMenuPlugin` JavaScript plugin was removed, use `triggerEvent` instead.
* The following blocks were moved from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/header.html.twig`.
  * `base_header`
  * `base_header_inner`
  * `base_navigation`
  * `base_navigation_inner`
  * `base_offcanvas_navigation`
  * `base_offcanvas_navigation_inner`
* The following blocks were moved from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/footer.html.twig`.
  * `base_footer`
  * `base_footer_inner`
* The template variable `page` in following templates was removed. Provide `header` or `footer` directly.
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/currency-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/top-bar.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`
* The template variables `activeId` and `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/categories.html.twig` were removed.
* The template variable `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig` was removed.
* The parameter `activeResult` of `src/Storefront/Resources/views/storefront/layout/sidebar/category-navigation.html.twig` was removed.
