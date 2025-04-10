---
title: Remove Accessibility Feature Flag and Storefront Deprecations
issue: NEXT-40495
---
# Core
* Changed the default of the feature flag `ACCESSIBILITY_TWEAKS` to `true`.
___
# Storefront
* Removed all occurrences of code related to the feature flag `ACCESSIBILITY_TWEAKS`. The new code behind that flag is now the default.
* Removed all occurrences of deprecated code, including the deletion of these deprecated files:

  * Removed deprecated file `storefront/src/plugin/main-menu/flyout-menu.plugin.js`.
  * Removed deprecated file `storefront/src/scss/layout/_navigation-flyout.scss`.
  * Removed deprecated file `storefront/layout/navigation/flyout.html.twig`.
  * Removed deprecated file `storefront/src/plugin/address-editor/address-editor.plugin.js`
  * Removed deprecated file `storefront/component/address/address-editor-modal-create-address.html.twig`
  * Removed deprecated file `component/address/address-editor-modal-list.html.twig`
  * Removed deprecated file `storefront/component/address/address-editor-modal.html.twig`
  * Removed deprecated file `storefront/src/scss/layout/_top-bar.scss`.
  * Removed deprecated file `storefront/src/scss/layout/_main-navigation.scss`.
  * Removed deprecated file `storefront/layout/navigation/navigation.html.twig`.
  * Removed deprecated file `storefront/layout/navigation/categories.html.twig`.
  * Removed deprecated file `storefront/src/scss/page/content/_breadcrumb.scss`.
  * Removed deprecated file `storefront/component/captcha/basicCaptchaFields.html.twig`.
  * Removed deprecated file `storefront/component/payment/payment-fields.html.twig`.
  * Removed deprecated file `storefront/page/checkout/cart/meta.html.twig`.
  * Removed deprecated file `storefront/page/account/register/meta.html.twig`.
  * Removed deprecated file `storefront/page/account/order-history/cancel-order-modal.html.twig`.
  * Removed deprecated file `storefront/page/account/profile/personal.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/configurator/select.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/properties.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/tabs.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/description.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/buy-widget.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/buy-widget-form.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/buy-widget-price.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/headline.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/review/review.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/review/review-form.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/review/review-item.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/review/review-login.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/review/review-widget.html.twig`.
  * Removed deprecated file `storefront/page/product-detail/cross-selling/tabs.html.twig`.
  * Removed deprecated file `storefront/utilities/alert.html.twig`.

  * Removed deprecated file `src/Storefront/Event/ThemeCompilerConcatenatedScriptsEvent.php`
  * Removed deprecated file `src/Storefront/Framework/Captcha/Exception/CaptchaInvalidException.php`
  * Removed deprecated file `src/Storefront/Framework/Twig/ControllerInfo.php`
  * Removed deprecated file `src/Storefront/Framework/Page/StorefrontSearchResult.php`
  * Removed deprecated file `src/Storefront/Theme/SalesChannelThemeLoader.php`
  * Removed deprecated file `src/Storefront/Theme/StorefrontPluginRegistryInterface.php`
  * Removed deprecated file `src/Storefront/Theme/ThemeFileImporter.php`
  * Removed deprecated file `src/Storefront/Theme/ThemeFileImporterInterface.php`

* Removed unused composer dependency `padaliyajay/php-autoprefixer`

* Added new Twig block `component_address_form_country_select` in `component/address/address-form.html.twig`.
* Added new Twig block `component_address_form_state_select` in `component/address/address-form.html.twig`.
* Added new Twig block `component_product_box_image_inner` in `component/product/card/box-standard.html.twig`.

* Deprecated Twig block `component_product_box_image_link_inner` for v6.8.0 in `component/product/card/box-standard.html.twig`, use `component_product_box_image_inner` instead.
