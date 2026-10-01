/**
 * @sw-package framework
 */

import * as bootstrap from 'bootstrap';

// log rejections so that they are not printed to stderr as a fallback
process.on('unhandledRejection', (reason) => {
    console.log('REJECTION', reason);
});

global.bootstrap = bootstrap;

global.router = {};

/**
 * The jsdom version bundled with jest 24 does not implement `Element.insertAdjacentElement`.
 * This polyfill only covers the two positions the storefront uses.
 */
if (!Element.prototype.insertAdjacentElement) {
    Element.prototype.insertAdjacentElement = function (position, element) {
        const where = String(position).toLowerCase();

        if (where === 'afterend') {
            if (!this.parentNode) {
                return null;
            }

            this.parentNode.insertBefore(element, this.nextSibling);
        } else if (where === 'beforeend') {
            this.appendChild(element);
        } else {
            throw new DOMException(`Position "${position}" is not covered by the jsdom polyfill in jest.init.js.`, 'SyntaxError');
        }

        return element;
    };
}

/**
 * Global mocks for "PluginManager" to avoid errors when testing JS Plugins.
 * The "Plugin" base class (plugin-system/plugin.class.js) uses "window.PluginManager" methods internally.
 * This leads to errors when instantiating "Plugin" classes in jest because "window.PluginManager" is undefined.
 */
global.PluginManager = {
    getPluginInstancesFromElement: () => {
        return new Map();
    },
    getPluginInstanceFromElement: () => {
        return {};
    },
    getPlugin: () => {
        return {
            get: () => [],
        };
    },
    getPluginInstances: () => {
        return new Map();
    },
};
