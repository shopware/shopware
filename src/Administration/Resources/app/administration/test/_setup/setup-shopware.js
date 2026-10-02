/**
 * @sw-package framework
 *
 * Initializes the Shopware global object and core registries for the test environment.
 * Previously provided by @shopware-ag/jest-preset-sw6-admin.
 */
const { join, resolve } = require('path');

const srcPath = global.adminPath;
if (!srcPath) {
    throw new Error('"globals.adminPath" is not defined. A file path to a Shopware 6 administration is required');
}

global.window._features_ = {};
global.window.startApplication = global.window.startApplication || (() => {});

/**
 * `src/core/shopware.ts` publishes the composables as `Shopware.Composables`. Loading them here would bind them
 * to their real imports before a spec's `jest.mock` calls apply, so each one is required when it is first read.
 * The getters are configurable, so specs can spy on them.
 */
jest.doMock(resolve(join(srcPath, 'src/app/composables/index')), () => {
    const names = require(join(srcPath, 'shopware-modules.json'))['shopware:composables'].exports;
    const file = (name) => name.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);

    return {
        __esModule: true,
        default: Object.defineProperties(
            {},
            Object.fromEntries(
                names.map((name) => [
                    name,
                    {
                        enumerable: true,
                        configurable: true,
                        get: () => require(join(srcPath, 'src/app/composables', file(name))).default,
                    },
                ]),
            ),
        ),
    };
});

const Shopware = require(resolve(join(srcPath, 'src/core/shopware.ts'))).ShopwareInstance;

const envBefore = process.env.NODE_ENV;

// vue.cjs.js loads different files based on NODE_ENV
process.env.NODE_ENV = 'production';

const { createApp } = require(resolve(join(srcPath, 'node_modules/vue/dist/vue.cjs.js')));
const app = createApp();
app.use(Shopware.Store._rootState);

process.env.NODE_ENV = envBefore;

module.exports = (() => {
    global.Shopware = Shopware;
    require(resolve(join(srcPath, 'src/app/mixin/index'))).default();
    require(resolve(join(srcPath, 'src/app/directive/index'))).default();
    require(resolve(join(srcPath, 'src/app/filter/index'))).default();
    require(resolve(join(srcPath, 'src/app/init-pre/state.init'))).default();
    require(resolve(join(srcPath, 'src/app/init/component-helper.init'))).default();
})();
