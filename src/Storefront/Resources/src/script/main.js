/*
import polyfills
 */
import 'src/script/helper/polyfill-loader.helper';

/*
import base requirements
 */
import 'bootstrap';

/*
import helpers
 */
import PluginManager from 'src/script/plugin-system/plugin.manager';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';
import NativeEventEmitter from 'src/script/helper/emitter.helper';

/*
import utils
 */
import AjaxModalExtensionUtil from 'src/script/utility/modal-extension/ajax-modal-extension.util';
import TimezoneUtil from 'src/script/utility/timezone/timezone.util';
import TooltipUtil from 'src/script/utility/tooltip/tooltip.util';

/*
import plugins
 */
import CartWidgetPlugin from 'src/script/plugin/header/cart-widget.plugin';
import SearchWidgetPlugin from 'src/script/plugin/header/search-widget.plugin';
import AccountMenuPlugin from 'src/script/plugin/header/account-menu.plugin';
import OffCanvasCartPlugin from 'src/script/plugin/offcanvas-cart/offcanvas-cart.plugin';
import AddToCartPlugin from 'src/script/plugin/add-to-cart/add-to-cart.plugin';
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';
import ScrollUpPlugin from 'src/script/plugin/scroll-up/scroll-up.plugin';
import CollapseFooterColumnsPlugin from 'src/script/plugin/collapse/collapse-footer-columns.plugin';
import FlyoutMenuPlugin from 'src/script/plugin/main-menu/flyout-menu.plugin';
import OffcanvasMenuPlugin from 'src/script/plugin/main-menu/offcanvas-menu.plugin';
import FormAutoSubmitPlugin from 'src/script/plugin/forms/form-auto-submit.plugin';
import FormAjaxSubmitPlugin from 'src/script/plugin/forms/form-ajax-submit.plugin';
import FormValidationPlugin from 'src/script/plugin/forms/form-validation.plugin';
import FormSubmitLoaderPlugin from 'src/script/plugin/forms/form-submit-loader.plugin';
import FormFieldTogglePlugin from 'src/script/plugin/forms/form-field-toggle.plugin';
import FromScrollToInvalidFieldPlugin from 'src/script/plugin/forms/form-scroll-to-invalid-field.plugin';
import OffCanvasTabsPlugin from 'src/script/plugin/offcanvas-tabs/offcanvas-tabs.plugin';
import BaseSliderPlugin from 'src/script/plugin/slider/base-slider.plugin';
import GallerySliderPlugin from 'src/script/plugin/slider/gallery-slider.plugin';
import ProductSliderPlugin from 'src/script/plugin/slider/product-slider.plugin';
import ZoomModalPlugin from 'src/script/plugin/zoom-modal/zoom-modal.plugin';
import MagnifierPlugin from 'src/script/plugin/magnifier/magnifier.plugin';
import ImageZoomPlugin from 'src/script/plugin/image-zoom/image-zoom.plugin';
import VariantSwitchPlugin from 'src/script/plugin/variant-switch/variant-switch.plugin';
import CmsSlotReloadPlugin from 'src/script/plugin/cms-slot-reload/cms-slot-reload.plugin';
import CmsSlotHistoryReloadPlugin from 'src/script/plugin/cms-slot-reload/cms-slot-history-reload.plugin';
import RemoteClickPlugin from 'src/script/plugin/remote-click/remote-click.plugin';
import AddressEditorPlugin from 'src/script/plugin/address-editor/address-editor.plugin';
import DateFormat from 'src/script/plugin/date-format/date-format.plugin';
import SetBrowserClassPlugin from 'src/script/plugin/set-browser-class/set-browser-class.plugin';
import FilterMultiSelectPlugin from 'src/script/plugin/filter/filter-multi-select.plugin';
import FilterPropertySelectPlugin from 'src/script/plugin/filter/filter-property-select.plugin';
import FilterBooleanPlugin from 'src/script/plugin/filter/filter-boolean.plugin';
import FilterRangePlugin from 'src/script/plugin/filter/filter-range.plugin';
import FilterRatingPlugin from 'src/script/plugin/filter/filter-rating.plugin';
import FilterPanelPlugin from 'src/script/plugin/filter/filter-panel.plugin';
import RatingSystemPlugin from 'src/script/plugin/rating-system/rating-system.plugin';

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
PluginManager.register('ScrollUp', ScrollUpPlugin, '[data-scroll-up]');
PluginManager.register('SearchWidget', SearchWidgetPlugin, '[data-search-form]');
PluginManager.register('CartWidget', CartWidgetPlugin, '[data-cart-widget]');
PluginManager.register('OffCanvasCart', OffCanvasCartPlugin, '[data-offcanvas-cart]');
PluginManager.register('AddToCart', AddToCartPlugin, '[data-add-to-cart]');
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, '[data-collapse-footer]');
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-flyout-menu]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu]');
PluginManager.register('FormValidation', FormValidationPlugin, '[data-form-validation]');
PluginManager.register('FormScrollToInvalidField', FromScrollToInvalidFieldPlugin, 'form');
PluginManager.register('FormSubmitLoader', FormSubmitLoaderPlugin, '[data-form-submit-loader]');
PluginManager.register('FormFieldToggle', FormFieldTogglePlugin, '[data-form-field-toggle]');
PluginManager.register('FormAutoSubmit', FormAutoSubmitPlugin, '[data-form-auto-submit]');
PluginManager.register('FormAjaxSubmit', FormAjaxSubmitPlugin, '[data-form-ajax-submit]');
PluginManager.register('AccountMenu', AccountMenuPlugin, '[data-offcanvas-account-menu]');
PluginManager.register('OffCanvasTabs', OffCanvasTabsPlugin, '[data-offcanvas-tabs]');
PluginManager.register('BaseSlider', BaseSliderPlugin, '[data-base-slider]');
PluginManager.register('GallerySlider', GallerySliderPlugin, '[data-gallery-slider]');
PluginManager.register('ProductSlider', ProductSliderPlugin, '[data-product-slider]');
PluginManager.register('ZoomModal', ZoomModalPlugin, '[data-zoom-modal]');
PluginManager.register('Magnifier', MagnifierPlugin, '[data-magnifier]');
PluginManager.register('ImageZoom', ImageZoomPlugin, '[data-image-zoom]');
PluginManager.register('VariantSwitch', VariantSwitchPlugin, '[data-variant-switch]');
PluginManager.register('CmsSlotReload', CmsSlotReloadPlugin, '[data-cms-slot-reload]');
PluginManager.register('CmsSlotHistoryReload', CmsSlotHistoryReloadPlugin, document);
PluginManager.register('RemoteClick', RemoteClickPlugin, '[data-remote-click]');
PluginManager.register('AddressEditor', AddressEditorPlugin, '[data-address-editor]');
PluginManager.register('SetBrowserClass', SetBrowserClassPlugin, 'html');
PluginManager.register('RatingSystem', RatingSystemPlugin, '[data-rating-system]');
PluginManager.register('FilterPanel', FilterPanelPlugin, '[data-filter-panel]');
PluginManager.register('FilterBoolean', FilterBooleanPlugin, '[data-filter-boolean]');
PluginManager.register('FilterRange', FilterRangePlugin, '[data-filter-range]');
PluginManager.register('FilterMultiSelect', FilterMultiSelectPlugin, '[data-filter-multi-select]');
PluginManager.register('FilterPropertySelect', FilterPropertySelectPlugin, '[data-filter-property-select]');
PluginManager.register('FilterRating', FilterRatingPlugin, '[data-filter-rating]');
PluginManager.register('RatingSystemPlugin', RatingSystemPlugin, '[data-rating-system]');

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => { PluginManager.initializePlugins() }, false);

/*
run utils
*/
new AjaxModalExtensionUtil();

new TimezoneUtil();

new TooltipUtil();
