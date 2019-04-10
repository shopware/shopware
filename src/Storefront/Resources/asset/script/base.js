// Provides polyfills based on the configured browser list
import '@babel/polyfill';
import 'asset/script/helper/polyfill-loader.helper';
import 'bootstrap';
import jQuery from 'jquery';

// Import styles
import 'asset/scss/base.scss';

// helpers
import PluginManager from 'asset/script/helper/plugin/plugin.manager';
import ViewportDetection from 'asset/script/helper/viewport-detection.helper';

// utils
import ModalExtensionUtil from 'asset/script/util/modal-extension/modal-extension.util';

// plugins
// import SimplePlugin from 'asset/script/plugin/_example/simple.plugin';
// import VanillaExtendPlugin from 'asset/script/plugin/_example/vanilla-extended.plugin';
// import ExtendedPlugin from 'asset/script/plugin/_example/extended.plugin';
// import OverriddenPlugin from 'asset/script/plugin/_example/overridden.plugin';

import CartWidgetPlugin from 'asset/script/plugin/actions/cart-widget.plugin';
import CartMiniPlugin from 'asset/script/plugin/cart-mini/cart-mini.plugin';
import CookiePermissionPlugin from 'asset/script/plugin/cookie-permission/cookie-permission.plugin';
import CollapseFooterColumnsPlugin from 'asset/script/plugin/collapse/collapse-footer-columns.plugin';
import SearchWidgetPlugin from 'asset/script/plugin/actions/search-widget/search-widget.plugin';
import FlyoutMenuPlugin from 'asset/script/plugin/main-menu/flyout-menu.plugin';
import OffcanvasMenuPlugin from 'asset/script/plugin/main-menu/offcanvas-menu.plugin';
import GuestModePlugin from 'asset/script/plugin/register/guest-mode.plugin';
import DifferentShippingPlugin from 'asset/script/plugin/register/different-shipping.plugin';
import FormValidationPlugin from 'asset/script/plugin/register/form-validation.plugin';
import FormSubmitLoaderPlugin from 'asset/script/plugin/forms/from-submit-loader.plugin';

// static plugins
import Logout from 'asset/script/plugin/logout/logout.plugin';
import OffCanvasAccountMenu from 'asset/script/plugin/off-canvas-account-menu/offcanvas-account-menu.plugin';

// pages
import 'asset/script/page/product-detail/product-detail.page';
import 'asset/script/page/account/addressbook.page';

/*
initialisation
*/
new ViewportDetection();
// Expose jQuery and plugin manager to the global window object
window.jQuery = jQuery;
window.$ = jQuery;

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}

/*
register plugins
*/

// example plugin (remove before release)
// PluginManager.register('Simple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.executePlugin('Simple', 'body');
// PluginManager.register('VanillaExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('VanillaExtendSimple', 'VanillaExtendSimple', VanillaExtendPlugin, 'body', { plugin: 'simple vanilla extend' });
//
// PluginManager.register('ExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('ExtendSimple', 'NewExtendSimple', ExtendedPlugin, 'body', { plugin: 'simple extend' });
//
// PluginManager.register('OverrideSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('OverrideSimple', 'OverrideSimple', OverriddenPlugin, 'body', { plugin: 'simple override' });
// example plugin end (remove before release)

PluginManager.register('SearchWidget', SearchWidgetPlugin, document);
PluginManager.register('CartWidget', CartWidgetPlugin, document);
PluginManager.register('CartMini', CartMiniPlugin, document);
PluginManager.register('CookiePermission', CookiePermissionPlugin, document);
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, '[data-collapse-footer]');
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-offcanvas-menu="true"]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu="true"]');
PluginManager.register('DifferentShipping', DifferentShippingPlugin, '*[data-different-shipping="true"]');
PluginManager.register('GuestMode', GuestModePlugin, '*[data-guest-mode="true"]');
PluginManager.register('FormValidation', FormValidationPlugin, '*[data-form-validation="true"]');
PluginManager.register('FormSubmitLoader', FormSubmitLoaderPlugin, '[data-form-submit-loader]');

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => PluginManager.executePlugins(), false);

/*
run utils
*/
new ModalExtensionUtil();

/*
run static classes
*/
new Logout();
new OffCanvasAccountMenu();
