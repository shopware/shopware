/**
 * @sw-package checkout
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
export { default as selectMtSelectOptionByText } from '../../../../../../test/_helper_/select-mt-select-by-text';

export const routes = [
    {
        name: 'sw.extension.my-extensions.listing.app',
        path: '/sw/extension/my-extensions/listing/app',
        query: {},
        component: {},
    },
    {
        name: 'sw.extension.my-extensions.listing.theme',
        path: '/sw/extension/my-extensions/listing/theme',
        query: {},
        component: {},
    },
];

export const shopwareService = new ShopwareService({}, {}, {}, {});
shopwareService.updateExtensionData = jest.fn();
shopwareService.installExtension = jest.fn(() => Promise.resolve());
shopwareService.installAndActivateExtension = jest.fn(() => Promise.resolve());
shopwareService.activateExtension = jest.fn(() => Promise.resolve());
shopwareService.deactivateExtension = jest.fn(() => Promise.resolve());
shopwareService.uninstallExtension = jest.fn(() => Promise.resolve());
shopwareService.updateExtension = jest.fn(() => Promise.resolve());

export const extensionStoreActionService = {
    downloadExtension: jest.fn(() => Promise.resolve()),
};

// The page uses the sw-extension-error mixin, which resolves this service to map error responses.
if (!Shopware.Service().list().includes('extensionErrorService')) {
    Shopware.Service().register('extensionErrorService', () => ({
        handleErrorResponse: jest.fn(() => []),
    }));
}

// Consent error the backend throws on update when an extension requires new permissions.
export function consentError(deltas = { permissions: {}, domains: [] }) {
    return {
        response: {
            data: {
                errors: [
                    {
                        code: 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
                        meta: { parameters: { deltas } },
                    },
                ],
            },
        },
    };
}

export function setMyExtensions(extensions) {
    Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);
}

export function makeCardStub({ emits = [] } = {}) {
    return {
        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
        props: [
            'extension',
            'selected',
            'bulkLoading',
        ],
        emits,
    };
}

export async function createWrapper({ aclCan = () => true, cardStub, query = {} } = {}) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    await router.push({ ...routes[0], query });
    await router.isReady();

    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing', {
            sync: true,
        }),
        {
            global: {
                plugins: [router],
                // The page declares the sw-extension-error mixin by name, resolve it explicitly for the test.
                mixins: [Shopware.Mixin.getByName('sw-extension-error')],
                stubs: {
                    'router-link': true,
                    'sw-self-maintained-extension-card': cardStub ?? {
                        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
                        props: ['extension'],
                    },
                    'sw-meteor-card': true,
                    'sw-extension-bulk-actions-bar': await wrapTestComponent('sw-extension-bulk-actions-bar', {
                        sync: true,
                    }),
                    'sw-pagination': await wrapTestComponent('sw-pagination', {
                        sync: true,
                    }),
                    'sw-field': true,
                    'sw-extension-my-extensions-listing-controls': await wrapTestComponent(
                        'sw-extension-my-extensions-listing-controls',
                        { sync: true },
                    ),

                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-skeleton': true,
                    'sw-external-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'sw-extension-component-section': true,
                    'sw-extension-permissions-modal': {
                        template: '<div class="sw-extension-permissions-modal" />',
                        props: [
                            'permissions',
                            'domains',
                            'title',
                            'description',
                            'actionLabel',
                            'extensionLabel',
                        ],
                    },
                    'sw-extension-bulk-uninstall-modal': {
                        template: '<div class="sw-extension-bulk-uninstall-modal" />',
                        props: [
                            'extensions',
                            'isLoading',
                        ],
                    },
                    'sw-extension-bulk-deactivation-modal': {
                        template: '<div class="sw-extension-bulk-deactivation-modal" />',
                        props: [
                            'extensions',
                            'isLoading',
                        ],
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {};
                        },
                    },
                    shopwareExtensionService: shopwareService,
                    extensionStoreActionService,
                    cacheApiService: {
                        clear: jest.fn(() => Promise.resolve()),
                    },
                    acl: {
                        can: aclCan,
                    },
                },
            },
            attachTo: document.body,
        },
    );
}

/**
 * Registers the store setup and mock resets every listing spec relies on.
 */
export function setupListingHooks() {
    beforeAll(() => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([{ name: 'Test', installedAt: null }]);

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                        },
                    },
                },
                api: {
                    assetsPath: '/',
                },
            }),
        });
    });

    beforeEach(async () => {
        setMyExtensions([
            {
                name: 'Test',
                installedAt: null,
            },
        ]);

        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = false;
        Shopware.Store.get('context').app.config.settings.appUrlReachable = true;

        shopwareService.updateExtensionData.mockClear();
        shopwareService.installExtension.mockClear();
        shopwareService.installExtension.mockResolvedValue(undefined);
        shopwareService.installAndActivateExtension.mockClear();
        shopwareService.installAndActivateExtension.mockResolvedValue(undefined);
        shopwareService.activateExtension.mockClear();
        shopwareService.activateExtension.mockResolvedValue(undefined);
        shopwareService.deactivateExtension.mockClear();
        shopwareService.deactivateExtension.mockResolvedValue(undefined);
        shopwareService.uninstallExtension.mockClear();
        shopwareService.uninstallExtension.mockResolvedValue(undefined);
        shopwareService.updateExtension.mockClear();
        shopwareService.updateExtension.mockResolvedValue(undefined);
        extensionStoreActionService.downloadExtension.mockClear();
        extensionStoreActionService.downloadExtension.mockResolvedValue(undefined);
    });
}
