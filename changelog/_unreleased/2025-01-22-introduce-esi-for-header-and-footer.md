---
title: Introduce ESI for header and footer
issue: NEXT-31674
author: Michael Telgmann
author_github: @mitelg
---
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

___
# Upgrade Information
## Introduction of ESI for header and footer
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

___
# Next Major Version Changes
## Introduction of ESI for header and footer
### Removals
* The properties `header` and `footer` and their getter and setter Methods in `\Shopware\Storefront\Framework\Twig\ErrorTemplateStruct` were removed.
* The properties `header`, `footer`, `salesChannelShippingMethods` and `salesChannelPaymentMethods` and their getter and setter Methods in `\Shopware\Storefront\Page\Page` were removed.
* The property `serviceMenu` and its getter and setter Methods in `\Shopware\Storefront\Pagelet\Header\HeaderPagelet` were removed.
* The `navigationId` request parameter in `\Shopware\Storefront\Pagelet\Header\HeaderPageletLoader::load` was removed.
* The `setNavigation` method in `\Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPagelet` was removed.
* The option `tiggerEvent` in `OffcanvasMenuPlugin` JavaScript plugin was removed.
* Moved the following blocks from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/header.html.twig`.
  * `base_header`
  * `base_header_inner`
  * `base_navigation`
  * `base_navigation_inner`
  * `base_offcanvas_navigation`
  * `base_offcanvas_navigation_inner`
* Moved the following blocks from `src/Storefront/Resources/views/storefront/base.html.twig` to `src/Storefront/Resources/views/storefront/layout/footer.html.twig`.
  * `base_footer`
  * `base_footer_inner`
* Removed the fallback from the template variable `page` to `header` or `footer` in following templates.
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/currency-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/header/top-bar.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`
* Removed the template variables `activeId` and `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/categories.html.twig`.
* Removed the template variable `activePath` in `src/Storefront/Resources/views/storefront/layout/navbar/navbar.html.twig`.
