/**
 * @package storefront
 */

/*
import polyfills
 */
import 'src/helper/polyfill-loader.helper';

/**
 * import base requirements
 */
import * as bootstrap from 'bootstrap';

/*
import helpers
 */
import Feature from 'src/helper/feature.helper';
import PluginManager from 'src/plugin-system/plugin.manager';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';
import FocusHandler from 'src/helper/focus-handler.helper';

/*
import utils
 */
import TimezoneUtil from 'src/utility/timezone/timezone.util';
import BootstrapUtil from 'src/utility/bootstrap/bootstrap.util';

/*
import plugins
 */
import SetBrowserClassPlugin from 'src/plugin/set-browser-class/set-browser-class.plugin';

window.eventEmitter = new NativeEventEmitter();
window.focusHandler = new FocusHandler();
window.bootstrap = bootstrap;

/*
initialisation
*/
new ViewportDetection();

/*
register plugins
*/
PluginManager.register('DateFormat', () => import('src/plugin/date-format/date-format.plugin'), '[data-date-format]');
PluginManager.register('ScrollUp', () => import('src/plugin/scroll-up/scroll-up.plugin'), '[data-scroll-up]');
PluginManager.register('SearchWidget', () => import('src/plugin/header/search-widget.plugin'), '[data-search-widget]');
PluginManager.register('CartWidget', () => import('src/plugin/header/cart-widget.plugin'), '[data-cart-widget]');
PluginManager.register('AccountGuestAbortButton', () => import('src/plugin/header/account-guest-abort-button.plugin'), '[data-account-guest-abort-button]')
PluginManager.register('OffCanvasCart', () => import('src/plugin/offcanvas-cart/offcanvas-cart.plugin'), '[data-off-canvas-cart]');
PluginManager.register('AddToCart', () => import('src/plugin/add-to-cart/add-to-cart.plugin'), '[data-add-to-cart]');
PluginManager.register('CollapseFooterColumns', () => import('src/plugin/collapse/collapse-footer-columns.plugin'), '[data-collapse-footer-columns]');
PluginManager.register('CollapseCheckoutConfirmMethods', () => import('src/plugin/collapse/collapse-checkout-confirm-methods.plugin'), '[data-collapse-checkout-confirm-methods]');
if (Feature.isActive('v6.7.0.0')) {
    PluginManager.register('Navbar', () => import('src/plugin/navbar/navbar.plugin'), '[data-navbar]');
} else {
    /** @deprecated tag:v6.7.0 - FlyoutMenu will be removed, see Navbar for the new implementation. */
    PluginManager.register('FlyoutMenu', () => import('src/plugin/main-menu/flyout-menu.plugin'), '[data-flyout-menu]');
}
PluginManager.register('OffCanvasMenu', () => import('src/plugin/main-menu/offcanvas-menu.plugin'), '[data-off-canvas-menu]');
PluginManager.register('FormValidation', () => import('src/plugin/forms/form-validation.plugin'), '[data-form-validation]');
PluginManager.register('FormScrollToInvalidField', () => import('src/plugin/forms/form-scroll-to-invalid-field.plugin'), 'form');
PluginManager.register('FormSubmitLoader', () => import('src/plugin/forms/form-submit-loader.plugin'), '[data-form-submit-loader]');
PluginManager.register('FormFieldToggle', () => import('src/plugin/forms/form-field-toggle.plugin'), '[data-form-field-toggle]');
PluginManager.register('FormAutoSubmit', () => import('src/plugin/forms/form-auto-submit.plugin'), '[data-form-auto-submit]');
PluginManager.register('FormAjaxSubmit', () => import('src/plugin/forms/form-ajax-submit.plugin'), '[data-form-ajax-submit]');
PluginManager.register('FormAddHistory', () => import('src/plugin/forms/form-add-history.plugin'), '[data-form-add-history]');
PluginManager.register('FormPreserver', () => import('src/plugin/forms/form-preserver.plugin'), '[data-form-preserver]');
PluginManager.register('AccountMenu', () => import('src/plugin/header/account-menu.plugin'), '[data-account-menu]');
PluginManager.register('OffCanvasTabs', () => import('src/plugin/offcanvas-tabs/offcanvas-tabs.plugin'), '[data-off-canvas-tabs]');
PluginManager.register('BaseSlider', () => import('src/plugin/slider/base-slider.plugin'), '[data-base-slider]');
PluginManager.register('GallerySlider', () => import('src/plugin/slider/gallery-slider.plugin'), '[data-gallery-slider]');
PluginManager.register('ProductSlider', () => import('src/plugin/slider/product-slider.plugin'), '[data-product-slider]');
PluginManager.register('ZoomModal', () => import('src/plugin/zoom-modal/zoom-modal.plugin'), '[data-zoom-modal]');
PluginManager.register('Magnifier', () => import('src/plugin/magnifier/magnifier.plugin'), '[data-magnifier]');
PluginManager.register('VariantSwitch', () => import('src/plugin/variant-switch/variant-switch.plugin'), '[data-variant-switch]');
PluginManager.register('RemoteClick', () => import('src/plugin/remote-click/remote-click.plugin'), '[data-remote-click]');
PluginManager.register('AddressEditor', () => import('src/plugin/address-editor/address-editor.plugin'), '[data-address-editor]');
PluginManager.register('SetBrowserClass', SetBrowserClassPlugin, 'html');
PluginManager.register('RatingSystem', () => import('src/plugin/rating-system/rating-system.plugin'), '[data-rating-system]');
PluginManager.register('Listing', () => import('src/plugin/listing/listing.plugin'), '[data-listing]');
PluginManager.register('OffCanvasFilter', () => import('src/plugin/offcanvas-filter/offcanvas-filter.plugin'), '[data-off-canvas-filter]');
PluginManager.register('FilterBoolean', () => import('src/plugin/listing/filter-boolean.plugin'), '[data-filter-boolean]');
PluginManager.register('FilterRange', () => import('src/plugin/listing/filter-range.plugin'), '[data-filter-range]');
PluginManager.register('FilterMultiSelect', () => import('src/plugin/listing/filter-multi-select.plugin'), '[data-filter-multi-select]');
PluginManager.register('FilterPropertySelect', () => import('src/plugin/listing/filter-property-select.plugin'), '[data-filter-property-select]');
PluginManager.register('FilterRatingSelect', () => import('src/plugin/listing/filter-rating-select.plugin'), '[data-filter-rating-select]');
PluginManager.register('ListingPagination', () => import('src/plugin/listing/listing-pagination.plugin'), '[data-listing-pagination]');
PluginManager.register('ListingSorting', () => import('src/plugin/listing/listing-sorting.plugin'), '[data-listing-sorting]');
PluginManager.register('CrossSelling', () => import('src/plugin/cross-selling/cross-selling.plugin'), '[data-cross-selling]');
PluginManager.register('DatePicker', () => import('src/plugin/date-picker/date-picker.plugin'), '[data-date-picker]'); // Not used in core, but implemented for plugins
PluginManager.register('FormCmsHandler', () => import('src/plugin/forms/form-cms-handler.plugin'), '.cms-element-form form');
PluginManager.register('CountryStateSelect', () => import('src/plugin/forms/form-country-state-select.plugin'), '[data-country-state-select]');
PluginManager.register('ClearInput', () => import('src/plugin/clear-input-button/clear-input.plugin'), '[data-clear-input]'); // Not used in core, but implemented for plugins
PluginManager.register('CmsGdprVideoElement', () => import('src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin'), '[data-cms-gdpr-video-element]');
PluginManager.register('BuyBox', () => import('src/plugin/buy-box/buy-box.plugin'), '[data-buy-box]');
PluginManager.register('BasicCaptcha', () => import('src/plugin/captcha/basic-captcha.plugin'), '[data-basic-captcha]');
PluginManager.register('QuantitySelector', () => import('src/plugin/quantity-selector/quantity-selector.plugin'), '[data-quantity-selector]');
PluginManager.register('AjaxModal', () => import('src/plugin/ajax-modal/ajax-modal.plugin'), '[data-ajax-modal][data-url]');

/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
PluginManager.register('SpatialGallerySliderViewerPlugin', () => import('src/plugin/spatial/spatial-gallery-slider-viewer.plugin'), '[data-spatial-gallery-slider-viewer]');
/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
PluginManager.register('SpatialZoomGallerySliderViewerPlugin', () => import('src/plugin/spatial/spatial-zoom-gallery-slider-viewer.plugin'), '[data-spatial-zoom-gallery-slider-viewer]');
/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
PluginManager.register('SpatialArViewer', () => import('src/plugin/spatial/spatial-ar-viewer-plugin'), '[data-spatial-ar-viewer]');
/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
PluginManager.register('PageQrcodeGenerator', () => import('src/plugin/qrcode/page-qrcode-generator'), '[data-page-qrcode-generator]');

if (window.useDefaultCookieConsent) {
    PluginManager.register('CookiePermission', () => import('src/plugin/cookie/cookie-permission.plugin'), '[data-cookie-permission]');
    PluginManager.register('CookieConfiguration', () => import('src/plugin/cookie/cookie-configuration.plugin'), '[data-cookie-permission]');
}

if (window.wishlistEnabled) {
    if (window.customerLoggedInState) {
        PluginManager.register('WishlistStorage', () => import('src/plugin/wishlist/persist-wishlist.plugin'), '[data-wishlist-storage]');
    } else {
        PluginManager.register('WishlistStorage', () => import('src/plugin/wishlist/local-wishlist.plugin'), '[data-wishlist-storage]');
        PluginManager.register('GuestWishlistPage', () => import('src/plugin/wishlist/guest-wishlist-page.plugin'), '[data-guest-wishlist-page]');
    }

    PluginManager.register('AddToWishlist', () => import('src/plugin/wishlist/add-to-wishlist.plugin'), '[data-add-to-wishlist]');
    PluginManager.register('WishlistWidget', () => import('src/plugin/header/wishlist-widget.plugin'), '[data-wishlist-widget]');
}

if (window.gtagActive) {
    PluginManager.register('GoogleAnalytics', () => import('src/plugin/google-analytics/google-analytics.plugin'));
}

if (window.googleReCaptchaV2Active) {
    PluginManager.register('GoogleReCaptchaV2', () => import('src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin'), '[data-google-re-captcha-v2]');
}

if (window.googleReCaptchaV3Active) {
    PluginManager.register('GoogleReCaptchaV3', () => import('src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin'), '[data-google-re-captcha-v3]');
}

window.Feature = Feature;

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => PluginManager.initializePlugins(), false);

// Set webpack publicPath at runtime because we don't know the theme seed hash when running webpack
// https://webpack-v3.jsx.app/guides/public-path/#on-the-fly
window.__webpack_public_path__ = window.themeJsPublicPath;

/*
run utils
*/
new TimezoneUtil();

BootstrapUtil.initBootstrapPlugins();
