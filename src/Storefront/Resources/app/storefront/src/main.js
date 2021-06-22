// merge 16.11.2020
/*
import polyfills
 */
import 'src/helper/polyfill-loader.helper';

/*
import base requirements
 */
import 'bootstrap';

/*
import helpers
 */
import Feature from 'src/helper/feature.helper';
import PluginManager from 'src/plugin-system/plugin.manager';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';

/*
import utils
 */
import AjaxModalExtensionUtil from 'src/utility/modal-extension/ajax-modal-extension.util';
import TimezoneUtil from 'src/utility/timezone/timezone.util';
import TooltipUtil from 'src/utility/tooltip/tooltip.util';

/*
import plugins
 */
import CartWidgetPlugin from 'src/plugin/header/cart-widget.plugin';
import SearchWidgetPlugin from 'src/plugin/header/search-widget.plugin';
import AccountMenuPlugin from 'src/plugin/header/account-menu.plugin';
import AccountGuestAbortButtonPlugin from 'src/plugin/header/account-guest-abort-button.plugin';
import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';
import AddToCartPlugin from 'src/plugin/add-to-cart/add-to-cart.plugin';
import CookiePermissionPlugin from 'src/plugin/cookie/cookie-permission.plugin';
import CookieConfigurationPlugin from 'src/plugin/cookie/cookie-configuration.plugin';
import ScrollUpPlugin from 'src/plugin/scroll-up/scroll-up.plugin';
import CollapseFooterColumnsPlugin from 'src/plugin/collapse/collapse-footer-columns.plugin';
import CollapseCheckoutConfirmMethodsPlugin from 'src/plugin/collapse/collapse-checkout-confirm-methods.plugin';
import FlyoutMenuPlugin from 'src/plugin/main-menu/flyout-menu.plugin';
import OffcanvasMenuPlugin from 'src/plugin/main-menu/offcanvas-menu.plugin';
import FormAutoSubmitPlugin from 'src/plugin/forms/form-auto-submit.plugin';
import FormAjaxSubmitPlugin from 'src/plugin/forms/form-ajax-submit.plugin';
import FormPreserverPlugin from 'src/plugin/forms/form-preserver.plugin';
import FormValidationPlugin from 'src/plugin/forms/form-validation.plugin';
import FormSubmitLoaderPlugin from 'src/plugin/forms/form-submit-loader.plugin';
import FormFieldTogglePlugin from 'src/plugin/forms/form-field-toggle.plugin';
import FromScrollToInvalidFieldPlugin from 'src/plugin/forms/form-scroll-to-invalid-field.plugin';
import OffCanvasTabsPlugin from 'src/plugin/offcanvas-tabs/offcanvas-tabs.plugin';
import BaseSliderPlugin from 'src/plugin/slider/base-slider.plugin';
import GallerySliderPlugin from 'src/plugin/slider/gallery-slider.plugin';
import ProductSliderPlugin from 'src/plugin/slider/product-slider.plugin';
import ZoomModalPlugin from 'src/plugin/zoom-modal/zoom-modal.plugin';
import MagnifierPlugin from 'src/plugin/magnifier/magnifier.plugin';
import VariantSwitchPlugin from 'src/plugin/variant-switch/variant-switch.plugin';
import CmsSlotReloadPlugin from 'src/plugin/cms-slot-reload/cms-slot-reload.plugin';
import CmsSlotHistoryReloadPlugin from 'src/plugin/cms-slot-reload/cms-slot-history-reload.plugin';
import RemoteClickPlugin from 'src/plugin/remote-click/remote-click.plugin';
import AddressEditorPlugin from 'src/plugin/address-editor/address-editor.plugin';
import DateFormat from 'src/plugin/date-format/date-format.plugin';
import SetBrowserClassPlugin from 'src/plugin/set-browser-class/set-browser-class.plugin';
import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import FilterPropertySelectPlugin from 'src/plugin/listing/filter-property-select.plugin';
import FilterBooleanPlugin from 'src/plugin/listing/filter-boolean.plugin';
import FilterRangePlugin from 'src/plugin/listing/filter-range.plugin';
import FilterRatingSelectPlugin from 'src/plugin/listing/filter-rating-select.plugin';
import ListingPlugin from 'src/plugin/listing/listing.plugin';
import OffCanvasFilterPlugin from 'src/plugin/offcanvas-filter/offcanvas-filter.plugin';
import RatingSystemPlugin from 'src/plugin/rating-system/rating-system.plugin';
import ListingPaginationPlugin from 'src/plugin/listing/listing-pagination.plugin';
import ListingSortingPlugin from 'src/plugin/listing/listing-sorting.plugin';
import DatePickerPlugin from 'src/plugin/date-picker/date-picker.plugin';
import FormCsrfHandlerPlugin from 'src/plugin/forms/form-csrf-handler.plugin';
import FormCmsHandlerPlugin from 'src/plugin/forms/form-cms-handler.plugin';
import CrossSellingPlugin from 'src/plugin/cross-selling/cross-selling.plugin';
import CountryStateSelectPlugin from 'src/plugin/forms/form-country-state-select.plugin';
import EllipsisPlugin from 'src/plugin/ellipsis/ellipsis.plugin';
import GoogleAnalyticsPlugin from 'src/plugin/google-analytics/google-analytics.plugin';
import GoogleReCaptchaV2Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin';
import GoogleReCaptchaV3Plugin from 'src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin';
import SwagBlockLink from 'src/helper/block-link.helper';
import StoreApiClient from 'src/service/store-api-client.service';
import ClearInputPlugin from 'src/plugin/clear-input-button/clear-input.plugin';
import CmsGdprVideoElement from 'src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin';
import WishlistWidgetPlugin from 'src/plugin/header/wishlist-widget.plugin';
import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import WishlistPersistStoragePlugin from 'src/plugin/wishlist/persist-wishlist.plugin';
import AddToWishlistPlugin from 'src/plugin/wishlist/add-to-wishlist.plugin';
import BuyBoxPlugin from 'src/plugin/buy-box/buy-box.plugin';
import GuestWishlistPagePlugin from 'src/plugin/wishlist/guest-wishlist-page.plugin';
import FadingPlugin from 'src/plugin/fading/fading.plugin';
import BasicCaptchaPlugin from 'src/plugin/captcha/basic-captcha.plugin';

window.eventEmitter = new NativeEventEmitter();

/*
initialisation
*/
new ViewportDetection();

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}

/*
register plugins
*/
PluginManager.register('DateFormat', DateFormat, '[data-date-format]');
PluginManager.register('CookiePermission', CookiePermissionPlugin, '[data-cookie-permission]');
PluginManager.register('CookieConfiguration', CookieConfigurationPlugin, '[data-cookie-permission]');
PluginManager.register('ScrollUp', ScrollUpPlugin, '[data-scroll-up]');
PluginManager.register('SearchWidget', SearchWidgetPlugin, '[data-search-form]');
PluginManager.register('CartWidget', CartWidgetPlugin, '[data-cart-widget]');

PluginManager.register('AccountGuestAbortButton', AccountGuestAbortButtonPlugin, '[data-account-guest-abort-button]')

PluginManager.register('OffCanvasCart', OffCanvasCartPlugin, '[data-offcanvas-cart]');
PluginManager.register('AddToCart', AddToCartPlugin, '[data-add-to-cart]');
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, '[data-collapse-footer]');
PluginManager.register('CollapseCheckoutConfirmMethods', CollapseCheckoutConfirmMethodsPlugin, '[data-collapse-checkout-confirm-methods]');
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-flyout-menu]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu]');
PluginManager.register('FormValidation', FormValidationPlugin, '[data-form-validation]');
PluginManager.register('FormScrollToInvalidField', FromScrollToInvalidFieldPlugin, 'form');
PluginManager.register('FormSubmitLoader', FormSubmitLoaderPlugin, '[data-form-submit-loader]');
PluginManager.register('FormFieldToggle', FormFieldTogglePlugin, '[data-form-field-toggle]');
PluginManager.register('FormAutoSubmit', FormAutoSubmitPlugin, '[data-form-auto-submit]');
PluginManager.register('FormAjaxSubmit', FormAjaxSubmitPlugin, '[data-form-ajax-submit]');
PluginManager.register('FormPreserver', FormPreserverPlugin, '[data-form-preserver]');
PluginManager.register('AccountMenu', AccountMenuPlugin, '[data-offcanvas-account-menu]');
PluginManager.register('OffCanvasTabs', OffCanvasTabsPlugin, '[data-offcanvas-tabs]');
PluginManager.register('BaseSlider', BaseSliderPlugin, '[data-base-slider]');
PluginManager.register('GallerySlider', GallerySliderPlugin, '[data-gallery-slider]');
PluginManager.register('ProductSlider', ProductSliderPlugin, '[data-product-slider]');
PluginManager.register('ZoomModal', ZoomModalPlugin, '[data-zoom-modal]');
PluginManager.register('Magnifier', MagnifierPlugin, '[data-magnifier]');
PluginManager.register('VariantSwitch', VariantSwitchPlugin, '[data-variant-switch]');
PluginManager.register('CmsSlotReload', CmsSlotReloadPlugin, '[data-cms-slot-reload]');
PluginManager.register('CmsSlotHistoryReload', CmsSlotHistoryReloadPlugin, document);
PluginManager.register('RemoteClick', RemoteClickPlugin, '[data-remote-click]');
PluginManager.register('AddressEditor', AddressEditorPlugin, '[data-address-editor]');
PluginManager.register('SetBrowserClass', SetBrowserClassPlugin, 'html');
PluginManager.register('RatingSystem', RatingSystemPlugin, '[data-rating-system]');
PluginManager.register('Listing', ListingPlugin, '[data-listing]');
PluginManager.register('OffCanvasFilter', OffCanvasFilterPlugin, '[data-offcanvas-filter]');
PluginManager.register('FilterBoolean', FilterBooleanPlugin, '[data-filter-boolean]');
PluginManager.register('FilterRange', FilterRangePlugin, '[data-filter-range]');
PluginManager.register('FilterMultiSelect', FilterMultiSelectPlugin, '[data-filter-multi-select]');
PluginManager.register('FilterPropertySelect', FilterPropertySelectPlugin, '[data-filter-property-select]');
PluginManager.register('FilterRatingSelect', FilterRatingSelectPlugin, '[data-filter-rating-select]');
PluginManager.register('ListingPagination', ListingPaginationPlugin, '[data-listing-pagination]');
PluginManager.register('ListingSorting', ListingSortingPlugin, '[data-listing-sorting]');
PluginManager.register('CrossSelling', CrossSellingPlugin, '[data-cross-selling]');
PluginManager.register('DatePicker', DatePickerPlugin, '[data-date-picker]');
PluginManager.register('FormCmsHandler', FormCmsHandlerPlugin, '.cms-element-form form');
PluginManager.register('CountryStateSelect', CountryStateSelectPlugin, '[data-country-state-select]');
PluginManager.register('Ellipsis', EllipsisPlugin, '[data-ellipsis]');
PluginManager.register('SwagBlockLink', SwagBlockLink, '[href="#not-found"]');
PluginManager.register('ClearInput', ClearInputPlugin, '[data-clear-input]');
PluginManager.register('CmsGdprVideoElement', CmsGdprVideoElement, '[data-cms-gdpr-video-element]');
PluginManager.register('BuyBox', BuyBoxPlugin, '[data-buy-box]');
PluginManager.register('Fading', FadingPlugin, '[data-fading]');
PluginManager.register('BasicCaptcha', BasicCaptchaPlugin, '[data-basic-captcha]');

if (window.wishlistEnabled) {
    if (window.customerLoggedInState) {
        PluginManager.register('WishlistStorage', WishlistPersistStoragePlugin, '[data-wishlist-storage]');
    } else {
        PluginManager.register('WishlistStorage', WishlistLocalStoragePlugin, '[data-wishlist-storage]');
        PluginManager.register('GuestWishlistPage', GuestWishlistPagePlugin, '[data-guest-wishlist-page]');
    }

    PluginManager.register('AddToWishlist', AddToWishlistPlugin, '[data-add-to-wishlist]');
    PluginManager.register('WishlistWidget', WishlistWidgetPlugin, '[data-wishlist-widget]');
}

if (window.csrf.enabled && window.csrf.mode === 'ajax') {
    PluginManager.register('FormCsrfHandler', FormCsrfHandlerPlugin, '[data-form-csrf-handler]');
}

if (window.gtagActive) {
    PluginManager.register('GoogleAnalytics', GoogleAnalyticsPlugin);
}

if (window.googleReCaptchaV2Active) {
    PluginManager.register('GoogleReCaptchaV2', GoogleReCaptchaV2Plugin, '[data-google-re-captcha-v2]');
}

if (window.googleReCaptchaV3Active) {
    PluginManager.register('GoogleReCaptchaV3', GoogleReCaptchaV3Plugin, '[data-google-re-captcha-v3]');
}

window.storeApiClient = StoreApiClient;

window.Feature = Feature;

/*
run plugins
*/
document.addEventListener('readystatechange', (event) => {
    if (event.target.readyState === 'complete') {
        PluginManager.initializePlugins();
    }
}, false);

/*
run utils
*/
new AjaxModalExtensionUtil();

new TimezoneUtil();

new TooltipUtil();

