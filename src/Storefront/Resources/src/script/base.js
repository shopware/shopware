/*
import polyfills
 */
import 'src/script/helper/polyfill-loader.helper';

/*
import base requirements
 */
import 'jquery/dist/jquery.slim';
import 'bootstrap';

/*
import styles
 */
import 'src/style/base.scss';

/*
import helpers
 */
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';

/*
import utils
 */
import AjaxModalExtensionUtil from 'src/script/utility/modal-extension/ajax-modal-extension.util';
import TimezoneUtil from 'src/script/utility/timezone/timezone.util.js';

/*
import plugins
 */
// import SimplePlugin from 'src/script/plugin/_example/simple.plugin';
// import VanillaExtendPlugin from 'src/script/plugin/_example/vanilla-extended.plugin';
// import ExtendedPlugin from 'src/script/plugin/_example/extended.plugin';
// import OverriddenPlugin from 'src/script/plugin/_example/overridden.plugin';

import CartWidgetPlugin from 'src/script/plugin/header/cart-widget.plugin';
import SearchWidgetPlugin from 'src/script/plugin/header/search-widget.plugin';
import AccountMenuPlugin from 'src/script/plugin/header/account-menu.plugin';
import OffCanvasCartPlugin from 'src/script/plugin/offcanvas-cart/offcanvas-cart.plugin';
import AddToCartPlugin from 'src/script/plugin/add-to-cart/add-to-cart.plugin';
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';
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
import ImageSliderPlugin from 'src/script/plugin/image-slider/image-slider.plugin';
import ZoomModalPlugin from 'src/script/plugin/zoom-modal/zoom-modal.plugin';
import MagnifierPlugin from 'src/script/plugin/magnifier/magnifier.plugin';
import ImageZoomPlugin from 'src/script/plugin/image-zoom/image-zoom.plugin';
import VariantSwitchPlugin from 'src/script/plugin/variant-switch/variant-switch.plugin';
import CmsSlotReloadPlugin from 'src/script/plugin/cms-slot-reload/cms-slot-reload.plugin';
import CmsSlotHistoryReloadPlugin from 'src/script/plugin/cms-slot-reload/cms-slot-history-reload.plugin';
import RemoteClickPlugin from 'src/script/plugin/remote-click/remote-click.plugin';
import AddressEditorPlugin from 'src/script/plugin/address-editor/address-editor.plugin';
import ConfirmOrderPlugin from 'src/script/plugin/confirm-order/confirm-order.plugin';
import DateFormat from 'src/script/plugin/date-format/date-format.plugin.js';
import SetBrowserClassPlugin from 'src/script/plugin/set-browser-class/set-browser-class.plugin';
import NativeEventEmitter from 'src/script/helper/emitter.helper';

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

// example plugin (remove before release)
// PluginManager.register('Simple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.initializePlugin('Simple', 'body');
// PluginManager.register('VanillaExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('VanillaExtendSimple', 'VanillaExtendSimple', VanillaExtendPlugin, 'body', { plugin: 'simple vanilla extend' });
//
// PluginManager.register('ExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('ExtendSimple', 'NewExtendSimple', ExtendedPlugin, 'body', { plugin: 'simple extend' });
//
// PluginManager.register('OverrideSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('OverrideSimple', 'OverrideSimple', OverriddenPlugin, 'body', { plugin: 'simple override' });
// example plugin end (remove before release)


PluginManager.register('DateFormat', DateFormat, '[data-date-format]');
PluginManager.register('CookiePermission', CookiePermissionPlugin, '[data-cookie-permission]');
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
PluginManager.register('ImageSlider', ImageSliderPlugin, '[data-image-slider]');
PluginManager.register('ZoomModal', ZoomModalPlugin, '[data-zoom-modal]');
PluginManager.register('Magnifier', MagnifierPlugin, '[data-magnifier]');
PluginManager.register('ImageZoom', ImageZoomPlugin, '[data-image-zoom]');
PluginManager.register('VariantSwitch', VariantSwitchPlugin, '[data-variant-switch]');
PluginManager.register('CmsSlotReload', CmsSlotReloadPlugin, '[data-cms-slot-reload]');
PluginManager.register('CmsSlotHistoryReload', CmsSlotHistoryReloadPlugin, document);
PluginManager.register('RemoteClick', RemoteClickPlugin, '[data-remote-click]');
PluginManager.register('AddressEditor', AddressEditorPlugin, '[data-address-editor]');
PluginManager.register('ConfirmOrder', ConfirmOrderPlugin, '[data-confirm-order]');
PluginManager.register('SetBrowserClass', SetBrowserClassPlugin, 'html');

/*
add configurations
*/
// // applicable via data-simple-plugin-config="myConfig"
// window.PluginConfigManager.add('SimplePlugin', 'myConfig', { some: 'options' });
// import ExtendedSimplePluginConfig from 'src/script/config/_example/extended-simple-plugin.config';
// window.PluginConfigManager.add('SimplePlugin', 'extendedConfig', ExtendedSimplePluginConfig);

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => PluginManager.initializePlugins(), false);

/*
run utils
*/
new AjaxModalExtensionUtil();

new TimezoneUtil();
