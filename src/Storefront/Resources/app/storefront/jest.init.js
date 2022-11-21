import '@babel/polyfill';
import bootstrap from 'bootstrap5';

/** @deprecated tag:v6.5.0 - jQuery will be removed. */
import $ from 'jquery';

// log rejections so that they are not printed to stderr as a fallback
process.on('unhandledRejection', (reason) => {
    console.log('REJECTION', reason);
});

global.bootstrap = bootstrap;
global.$ = global.jQuery = $;
global.$.fn.tooltip = jest.fn();
global.$.fn.popover = jest.fn();

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
