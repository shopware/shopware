// Provides polyfills based on the configured browser list
import '@babel/polyfill';
import 'bootstrap';
import jQuery from 'jquery';

// Import styles
import './assets/sass/main.scss';

import Client from './service/http-client.service';
import CartMini from './plugins/cart-mini/CartMini';
import CartWidget from './plugins/actions/CartWidget';

import SimplePlugin from './plugins/test/simple-plugin';
import ExtendedPlugin from './plugins/test/extended-plugin';
import pluginManager from './helper/plugin.manager';

// Expose jQuery and plugin manager to the global window object
window.jQuery = jQuery;
window.$ = jQuery;
window.$pluginManager = pluginManager;

const client = new Client(window.accessKey, window.contextToken);
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
new CartWidget(); // eslint-disable-line no-new

// Cart Mini OffCanvas
new CartMini(); // eslint-disable-line no-new
