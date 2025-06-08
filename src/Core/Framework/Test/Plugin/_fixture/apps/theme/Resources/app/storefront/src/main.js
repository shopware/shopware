import ExamplePlugin from './example-plugin/example-plugin.js';

const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin);
