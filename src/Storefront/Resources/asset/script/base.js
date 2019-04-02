// Provides polyfills based on the configured browser list
import '@babel/polyfill';
import 'form-association-polyfill/register-with-polyfills'
import 'bootstrap';
import jQuery from 'jquery';

// Import styles
import '../scss/base.scss';

// helpers
import PluginManager from './helper/plugin/plugin.manager';
import ViewportDetection from "./helper/viewport-detection.helper";
import HttpClient from './service/http-client.service';

// utils
import ModalExtensionUtil from "./util/modal-extension/modal-extension.util";

// plugins
import SimplePlugin from './plugin/_example/simple.plugin';
import VanillaExtendPlugin from './plugin/_example/vanilla-extended.plugin';
import ExtendedPlugin from './plugin/_example/extended.plugin';
import OverriddenPlugin from './plugin/_example/overridden.plugin';

import CartWidgetPlugin from './plugin/actions/cart-widget.plugin';
import CartMiniPlugin from './plugin/cart-mini/cart-mini.plugin';
import CookiePermissionPlugin from './plugin/cookie-permission/cookie-permission.plugin';
import CollapseFooterColumnsPlugin from "./plugin/collapse/collapse-footer.plugin";
import SearchWidgetPlugin from "./plugin/actions/search-widget/search-widget.plugin";
import FlyoutMenuPlugin from "./plugin/main-menu/flyout-menu.plugin";
import OffcanvasMenuPlugin from "./plugin/main-menu/offcanvas-menu.plugin";


// static plugins
import Logout from "./plugin/logout/logout.plugin";
import OffCanvasAccountMenu from "./plugin/off-canvas-account-menu/offcanvas-account-menu.plugin";

// pages
import './page/product-detail/product-detail.page';
import './page/account/register.page';
import './page/account/addressbook.page';
import './page/account/profile.page';
import './page/checkout/confirm.page';

// Import styles
import '../scss/base.scss';

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
http client example
*/
const client = new HttpClient(window.accessKey, window.contextToken);
client.get('/storefront-api/v1/product?page=1&limit=10', (response) => {
    console.log('client response', JSON.parse(response));
});

/*
register plugins
*/

// example plugin (remove before release)
PluginManager.register('Simple', SimplePlugin, 'body', { plugin: 'simple' });
PluginManager.register('VanillaExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
PluginManager.extend('VanillaExtendSimple', 'VanillaExtendSimple', VanillaExtendPlugin, 'body', { plugin: 'simple vanilla extend' });

PluginManager.register('ExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
PluginManager.extend('ExtendSimple', 'NewExtendSimple', ExtendedPlugin, 'body', { plugin: 'simple extend' });

PluginManager.register('OverrideSimple', SimplePlugin, 'body', { plugin: 'simple' });
PluginManager.extend('OverrideSimple', 'OverrideSimple', OverriddenPlugin, 'body', { plugin: 'simple override' });
// example plugin end (remove before release)

PluginManager.register('SearchWidget', SearchWidgetPlugin, document);
PluginManager.register('CartWidget', CartWidgetPlugin, document);
PluginManager.register('CartMini', CartMiniPlugin, document);
PluginManager.register('CookiePermission', CookiePermissionPlugin, document);
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, document);
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-offcanvas-menu="true"]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu="true"]');

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
