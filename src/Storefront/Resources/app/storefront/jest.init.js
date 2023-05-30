/**
 * @package storefront
 */

import * as bootstrap from 'bootstrap';

// log rejections so that they are not printed to stderr as a fallback
process.on('unhandledRejection', (reason) => {
    console.log('REJECTION', reason);
});

global.bootstrap = bootstrap;

global.router = {};

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
