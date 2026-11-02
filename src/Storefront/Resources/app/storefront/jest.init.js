/**
 * @sw-package framework
 */

import * as bootstrap from 'bootstrap';
import Feature from 'src/helper/feature.helper';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';
import getMajorFeatureFlags from './test/_helper_/majorFeatureFlags';

const featureConfig = parse(readFileSync(resolve(__dirname, '../../../../Core/Framework/Resources/config/packages/feature.yaml'), 'utf8'));
const activeFlags = getMajorFeatureFlags(featureConfig, process.env);
Feature.init(Object.fromEntries(featureConfig.shopware.feature.flags
    .filter(({ name }) => activeFlags.includes(name.toUpperCase().replace(/[.:-]/g, '_')))
    .map(({ name }) => [name, true])));

// FormHandler reads the flag while its class is evaluated, before any test beforeEach runs.
window.Feature = Feature;

it.deprecated = (removedIn) => Feature.isActive(removedIn) ? it.skip : it;
describe.deprecated = (removedIn) => Feature.isActive(removedIn) ? describe.skip : describe;

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
    initializePlugins: jest.fn(() => Promise.resolve()),
    initializePluginsInParentElement: jest.fn(() => Promise.resolve()),
};
