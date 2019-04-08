/*
import polyfills
 */
import '@babel/polyfill';
import 'asset/script/helper/polyfill-loader.helper';

/*
import base requirements
 */
import 'bootstrap';
import jQuery from 'jquery';

/*
import styles
 */
import 'asset/scss/base.scss';

/*
import helpers
 */
import PluginManager from 'asset/script/helper/plugin/plugin.manager';
import ViewportDetection from 'asset/script/helper/viewport-detection.helper';

/*
import utils
 */
import ModalExtensionUtil from 'asset/script/util/modal-extension/modal-extension.util';

/*
import plugins
 */
// import SimplePlugin from 'asset/script/plugin/_example/simple.plugin';
// import VanillaExtendPlugin from 'asset/script/plugin/_example/vanilla-extended.plugin';
// import ExtendedPlugin from 'asset/script/plugin/_example/extended.plugin';
// import OverriddenPlugin from 'asset/script/plugin/_example/overridden.plugin';

import CartWidgetPlugin from 'asset/script/plugin/header/cart-widget.plugin';
import SearchWidgetPlugin from 'asset/script/plugin/header/search-widget/search-widget.plugin';
import AccountMenuPlugin from 'asset/script/plugin/header/account-menu.plugin';
import CartMiniPlugin from 'asset/script/plugin/cart-mini/cart-mini.plugin';
import CookiePermissionPlugin from 'asset/script/plugin/cookie-permission/cookie-permission.plugin';
import CollapseFooterColumnsPlugin from 'asset/script/plugin/collapse/collapse-footer-columns.plugin';
import FlyoutMenuPlugin from 'asset/script/plugin/main-menu/flyout-menu.plugin';
import OffcanvasMenuPlugin from 'asset/script/plugin/main-menu/offcanvas-menu.plugin';
import GuestModePlugin from 'asset/script/plugin/register/guest-mode.plugin';
import DifferentShippingPlugin from 'asset/script/plugin/register/different-shipping.plugin';
import FormValidationPlugin from 'asset/script/plugin/register/form-validation.plugin';
import FormSubmitLoaderPlugin from 'asset/script/plugin/forms/from-submit-loader.plugin';
import OffCanvasTabsPlugin from 'asset/script/plugin/off-canvas-tabs/offcanvas-tabs.plugin';

/*
import static plugins
 */
import Logout from 'asset/script/plugin/logout/logout.plugin';

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


PluginManager.register('CookiePermission', CookiePermissionPlugin, '[data-cookie-permission]');
PluginManager.register('SearchWidget', SearchWidgetPlugin, '[data-search-form]');
PluginManager.register('CartWidget', CartWidgetPlugin, '[data-cart-widget]');
PluginManager.register('CartMini', CartMiniPlugin, '[data-cart-mini]');
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, '[data-collapse-footer]');
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-offcanvas-menu]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu]');
PluginManager.register('DifferentShipping', DifferentShippingPlugin, '[data-different-shipping]');
PluginManager.register('GuestMode', GuestModePlugin, '[data-guest-mode]');
PluginManager.register('FormValidation', FormValidationPlugin, '[data-form-validation]');
PluginManager.register('FormSubmitLoader', FormSubmitLoaderPlugin, '[data-form-submit-loader]');
PluginManager.register('AccountMenu', AccountMenuPlugin, '[data-offcanvas-account-menu]');
PluginManager.register('OffCanvasTabs', OffCanvasTabsPlugin, '[data-offcanvas-tab]');

/*
pages
 */
import 'asset/script/page/product-detail/product-detail.page';
import 'asset/script/page/account/addressbook.page';
import 'asset/script/page/account/profile.page';

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
