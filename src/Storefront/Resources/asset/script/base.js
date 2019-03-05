// Provides polyfills based on the configured browser list
import '@babel/polyfill';
import 'bootstrap';
import jQuery from 'jquery';

// Import styles
import '../scss/base.scss';

import './page/account/register.page';

import HttpClient from './service/http-client.service';
import CartMini from './plugin/cart-mini/cart-mini.plugin';
import CartWidget from './plugin/actions/cart-widget.plugin';
import CookiePermission from './plugin/cookie-permission/cookie-permission.plugin';
import SimplePlugin from './plugin/test/simple-plugin';
import ExtendedPlugin from './plugin/test/extended-plugin';
import ModalExtension from "./plugin/modal/modal-extension.plugin";
import pluginManager from './helper/plugin.manager';
import ViewportDetection from "./helper/viewport-detection.helper";

// Expose jQuery and plugin manager to the global window object
window.jQuery = jQuery;
window.$ = jQuery;
window.$pluginManager = pluginManager;

new ViewportDetection();

const client = new HttpClient(window.accessKey, window.contextToken);
client.get('/storefront-api/v1/product?page=1&limit=10', (response) => {
    console.log('client response', JSON.parse(response));
});

pluginManager.register('simplePlugin', {
    plugin: SimplePlugin,
    selector: '*[data-simple-plugin="true"]'
});

pluginManager.register('extendedPlugin', {
    plugin: ExtendedPlugin,
    selector: '*[data-extended-plugin="true"]'
});

document.addEventListener('DOMContentLoaded', () => {
    const jQueryInstance = window.$;
    const plugins = pluginManager.run(jQueryInstance);

    // Initialize plugins
    Object.entries(plugins).forEach((plugin) => {
        const [name, definition] = plugin;
        jQuery(definition.selector)[name]();
    });
}, false);


// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}


// Header Cart Widget
new CartWidget();

// Cart Mini OffCanvas
new CartMini();

// Modal Extension
new ModalExtension();

// Cookie Permission
new CookiePermission();
