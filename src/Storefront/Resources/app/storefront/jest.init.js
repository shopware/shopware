/**
 * @package storefront
 */

import '@babel/polyfill';
import * as bootstrap from 'bootstrap';

// log rejections so that they are not printed to stderr as a fallback
process.on('unhandledRejection', (reason) => {
    console.log('REJECTION', reason);
});

global.bootstrap = bootstrap;

// Global mocks for common window properties
/** @deprecated tag:v6.5.0 - window property csrf will be removed */
global.csrf = {
    enabled: false,
};

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
