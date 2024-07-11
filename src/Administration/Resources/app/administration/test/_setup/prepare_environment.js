/**
 * @package admin
 */

import { config, enableAutoUnmount } from '@vue/test-utils';
import Vue, { compatUtils } from 'vue';

// eslint-disable-next-line import/no-extraneous-dependencies
import '@testing-library/jest-dom';

// eslint-disable-next-line import/no-extraneous-dependencies
import VirtualCallStackPlugin from 'src/app/plugin/virtual-call-stack.plugin';
import aclService from './_mocks_/acl.service.mock';
import feature from './_mocks_/feature.service.mock';
import repositoryFactory from './_mocks_/repositoryFactory.service.mock';
import flushPromises from '../_helper_/flushPromises';
import wrapTestComponent from '../_helper_/componentWrapper';
import resetFilters from '../_helper_/restartFilters';
import 'blob-polyfill';
import { sendTimeoutExpired } from '../_helper_/allowedErrors';

// Setup Vue Test Utils configuration
config.showDeprecationWarnings = true;
config.global.config.compilerOptions = {
    ...config.global.config.compilerOptions,
    whitespace: 'preserve',
};

// enable autoUnmount for wrapper after each test
enableAutoUnmount(afterEach);

// Make common utils available globally as well
global.Vue = Vue;

// Add all directives
const directiveRegistry = Shopware.Directive.getDirectiveRegistry();
directiveRegistry.forEach((value, key) => {
    if (key === 'tooltip') {
        global.Vue.directive('tooltip', {
            beforeMount(el, binding) {
                el.setAttribute('tooltip-mock-id', 'RANDOM_ID');
                el.setAttribute('tooltip-mock-message', binding.value.message);
                el.setAttribute('tooltip-mock-disabled', binding.value.disabled);
            },
            mounted(el, binding) {
                el.setAttribute('tooltip-mock-id', 'RANDOM_ID');
                el.setAttribute('tooltip-mock-message', binding.value.message);
                el.setAttribute('tooltip-mock-disabled', binding.value.disabled);
            },
            updated(el, binding) {
                el.setAttribute('tooltip-mock-id', 'RANDOM_ID');
                el.setAttribute('tooltip-mock-message', binding.value.message);
                el.setAttribute('tooltip-mock-disabled', binding.value.disabled);
            },
        });
        return;
    }

    if (key === 'popover') {
        global.Vue.directive('popover', {});
        return;
    }

    global.Vue.directive(key, value);
});

// Add all filters
const filterRegistry = Shopware.Filter.getRegistry();
filterRegistry.forEach((value, key) => {
    if (compatUtils.checkCompatEnabled('FILTERS')) {
        global.Vue.filter(key, value);
    }
});

// Add services
Shopware.Service().register('acl', () => aclService);
Shopware.Service().register('feature', () => feature);
Shopware.Feature = Shopware.Service('feature');
Shopware.Service().register('repositoryFactory', () => repositoryFactory);

// Provide all services
Shopware.Service().list().forEach(serviceKey => {
    config.global.provide[serviceKey] = Shopware.Service(serviceKey);
});

// Set important functions for Shopware Core
Shopware.Application.view = {
    setReactive: (target, propertyName, value) => {
        if (compatUtils.isCompatEnabled('GLOBAL_SET')) {
            return Vue.set(target, propertyName, value);
        }

        // eslint-disable-next-line no-return-assign
        return target[propertyName] = value;
    },
    deleteReactive(target, propertyName) {
        Vue.delete(target, propertyName);
    },
    root: {
        $tc: v => v,
    },
    i18n: {
        global: {
            tc: v => v,
            te: v => v,
            t: v => v,
        },
    },
};

// Prepare Context
Shopware.State.commit('context/setApiInstallationPath', 'installationPath');
Shopware.State.commit('context/setApiApiPath', '/api');
Shopware.State.commit('context/setApiApiResourcePath', '/api/v3');
Shopware.State.commit('context/setApiAssetsPath', '');
Shopware.State.commit('context/setApiLanguageId', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');
Shopware.State.commit('context/setApiInheritance', false);
Shopware.State.commit('context/setApiSystemLanguageId', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');
Shopware.State.commit('context/setApiLiveVersionId', '0fa91ce3e96a4bc2be4bd9ce752c3425');
Shopware.Context.api.authToken = {
    // eslint-disable-next-line max-len
    access: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImI0MTdkYjQ1MzMwNTY1MGIyY2QxMWVhYTBmZjRjNWJmZTVjZWYxYTI3NzBjY2JmY2M3MGY2Y2FiZDIzYWQyYmZiMzc1NTZhNDFlNGE3M2M5In0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6ImI0MTdkYjQ1MzMwNTY1MGIyY2QxMWVhYTBmZjRjNWJmZTVjZWYxYTI3NzBjY2JmY2M3MGY2Y2FiZDIzYWQyYmZiMzc1NTZhNDFlNGE3M2M5IiwiaWF0IjoxNjAyODM5OTgxLCJuYmYiOjE2MDI4Mzk5ODEsImV4cCI6MTYwMjg0MDU4MSwic3ViIjoiNzk5Y2NmNzY3MzZjNDkxYTgzNTA5MzA0Mjc3YzI3MTkiLCJzY29wZXMiOlsid3JpdGUiLCJhZG1pbiJdfQ.Df0EnZyZ-eY1iNCB-0x-0Ir8a8XW_HOdhq9HEcx7AbCEogHIFtU_0UPxTLX9_Wo3r-5C4FmbQrN31ReBWxkbEldMb3EU-UL4FIJA2gYhFWAXV2ZhaEJ5hRQ04n4gra0Os48vzYIEOq87_0lPPQqqVZLi68aHLVSF962VE1SkbofKqS2l2mDh9JJjnyhZavpkmpLhLkoWBBUWJS7G-EHo_-DttxPpA8W0Kgyg8Ch4Z2xqZ1r0zaB6hIS97-m8qLFHtjPhrbLW8NIMURIU3_brkkO2wFXrLKc0Y6MLJac8BVEe8VTEoEo8x8Ft2dCQU5aF2Aht3Y_55m1VjUMXBSb77A',
    expiry: 1602840582,
    // eslint-disable-next-line max-len
    refresh: 'def5020065a671fb38ec810a50bb627db679fd9a046ca0187215d418986fce75d3b55f7e0588c33318f3f7a280edc1e82b764a6b1fb82275e457459e58fff73afaa2aac08acd23322d398a74babbd9e02c11228985a5f140742eaa2c30af55ae350aca32e898ca9a5955c0bf057dee2b39bb5134aa6176668744fe05d6dbc9a0294bf6fa4dd6b4b07ed5d235d89005eeffc0e69ddc072e2023e522a5fd699c3e68b1dcdcc9f60c63f62ff4ed1778abfb0f3b95c4b44ad92d885bf1dca115f086b1a2368e7326f467331b6a0e65049e790c4d3a35fc1d77dfbd91da74c4d7cc449604adecd41bd84596efa4651b75bef0eeba6aef0d33338be22bf4e816584aefce9588a85d1dafbe311e330835d54dc19f43baa7a7ad63ee9573c98444219d80266b52b6e840354596d369e8350f3df18dae21a9dc607dcf70d66ddf78652a0d4083b85a832cc808d61ad15c196e1579cdea3829a8b480572f7afd590cd18fe811b5596554a58c5800756fdb1c051a461e4d7cf7c94c552ccf79d7a1368dfe8e63f4402abbaa6cabbd92437cf3f78c302ea7492dd60f5cfd8f7b4e8aa714',
};
Shopware.State.commit('setAdminLocale', {
    locales: ['en-GB'],
    locale: 'en-GB',
    languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
});

// Add global mocks
config.global.mocks = {
    $tc: v => v,
    $t: v => v,
    $te: () => true,
    $sanitize: key => key,
    $i18n: {
        locale: 'en-GB',
        fallbackLocale: 'en-GB',
        messages: {
            'en-GB': {},
        },
    },
    $device: {
        onResize: jest.fn(),
        removeResizeListener: jest.fn(),
        getSystemKey: jest.fn(() => 'CTRL'),
        getViewportWidth: jest.fn(() => 1920),
    },
    $router: {
        replace: jest.fn(),
        push: jest.fn(),
        go: jest.fn(),
        resolve: jest.fn(() => {
            return {
                matched: [],
            };
        }),
    },
    $route: {
        params: {},
    },
    $store: Shopware.State._store,
};

config.global.stubs = {
    'sw-modal': {
        template: `
        <div class="sw-modal">
            <slot name="modal-header">
                <slot name="modal-title"></slot>
            </slot>
            <slot name="modal-body">
                 <slot></slot>
            </slot>
            <slot name="modal-footer">
            </slot>
        </div>
    `,
    },
    ...config.global.stubs,
};

// Add global plugins
config.global.plugins = [
    // isCompatEnabled method plugin
    {
        install: (app) => {
            app.config.globalProperties.isCompatEnabled = function (key) {
                return this.$options.compatConfig?.[key] ?? !window._features_.DISABLE_VUE_COMPAT;
            };
        },
    },
    VirtualCallStackPlugin,
];

global.allowedErrors = [
    {
        method: 'warn',
        msg: 'No extension found for origin ""',
    },
    {
        method: 'error',
        msgCheck: (msg) => {
            if (typeof msg !== 'string') {
                return false;
            }

            return msg.includes('you tried to publish is already registered');
        },
    },
    {
        method: 'warn',
        msgCheck: (msg) => {
            if (typeof msg !== 'string') {
                return false;
            }

            return msg.includes('has already been registered in target app');
        },
    },
    {
        method: 'warn',
        msgCheck: (msg0, msg1) => {
            if (typeof msg0 !== 'string') {
                return false;
            }

            return msg0?.includes('is deprecated and will be removed in v6.7.0.0. Please use') ||
                msg1?.includes?.('is deprecated and will be removed in v6.7.0.0. Please use');
        },
    },
    sendTimeoutExpired,
];

global.flushPromises = flushPromises;
global.wrapTestComponent = wrapTestComponent;
global.resetFilters = resetFilters;

let consoleHasError = false;
let consoleHasWarning = false;
let errorArgs = null;
let warnArgs = null;
const { error, warn } = console;

global.console.error = (...args) => {
    let silenceError = false;
    // eslint-disable-next-line array-callback-return
    global.allowedErrors.some(allowedError => {
        if (allowedError.method !== 'error') {
            return;
        }

        if (typeof allowedError.msg === 'string') {
            if (typeof args[0] === 'string') {
                const shouldBeSilenced = args[0].includes(allowedError.msg);

                if (shouldBeSilenced) {
                    silenceError = true;
                }
            }
            return;
        }

        if (typeof allowedError.msgCheck === 'function') {
            if (allowedError.msgCheck) {
                const shouldBeSilenced = allowedError.msgCheck(args[0]);

                if (shouldBeSilenced) {
                    silenceError = true;
                }
            }

            return;
        }

        const shouldBeSilenced = allowedError.msg && allowedError.msg.test(args[0]);

        if (shouldBeSilenced) {
            silenceError = true;
        }
    });

    if (!silenceError) {
        consoleHasError = true;
        errorArgs = args;
        error(...args);
    }
};

if (!process.env.DISABLE_JEST_COMPAT_MODE) {
    // Mute warnings for now as they are expected due to compat options
    global.console.warn = () => {};
} else {
    global.console.warn = (...args) => {
        let silenceWarning = false;
        // eslint-disable-next-line array-callback-return
        global.allowedErrors.some(allowedError => {
            if (allowedError.method !== 'warn') {
                return;
            }

            if (typeof allowedError.msg === 'string') {
                if (typeof args[0] === 'string') {
                    const shouldBeSilenced = args[0].includes(allowedError.msg);

                    if (shouldBeSilenced) {
                        silenceWarning = true;
                    }
                }
                return;
            }

            if (typeof allowedError.msgCheck === 'function') {
                if (allowedError.msgCheck) {
                    const shouldBeSilenced = allowedError.msgCheck(args[0], args[1]);

                    if (shouldBeSilenced) {
                        silenceWarning = true;
                    }
                }

                return;
            }

            const shouldBeSilenced = allowedError.msg && allowedError.msg.test(args[0]);

            if (shouldBeSilenced) {
                silenceWarning = true;
            }
        });

        if (!silenceWarning) {
            consoleHasWarning = true;
            warnArgs = args;
            warn(...args);
        }
    };
}

// eslint-disable-next-line jest/require-top-level-describe
beforeEach(() => {
    consoleHasError = false;
    errorArgs = null;
    global.activeFeatureFlags = [];
});

// eslint-disable-next-line jest/require-top-level-describe
afterEach(() => {
    if (consoleHasError) {
        // reset variable for next test
        consoleHasError = false;

        if (errorArgs) {
            throw new Error(...errorArgs);
        }

        throw new Error('A console.error occurred without any arguments.');
    }

    if (consoleHasWarning) {
        // reset variable for next test
        consoleHasWarning = false;

        if (warnArgs) {
            throw new Error(...warnArgs);
        }

        throw new Error('A console.warn occurred without any arguments.');
    }
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});
