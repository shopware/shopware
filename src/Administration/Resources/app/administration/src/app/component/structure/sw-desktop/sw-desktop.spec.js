/**
 * @sw-package framework
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

const routes = [
    {
        name: 'sw.dashboard.index',
        path: '/sw/dashboard/index',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                name: 'dashboard',
            },
        },
    },
    {
        name: 'sw.product.index',
        path: '/sw/product/index',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'sw-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: { index: { name: 'sw.product.index' } },
            },
        },
    },
    {
        name: 'sw.product.create.base',
        path: '/sw/product/create/base',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'sw-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: {
                    index: { name: 'sw.product.index' },
                    create: {
                        children: [
                            {
                                name: 'sw.product.create.base',
                            },
                        ],
                        name: 'sw.product.create',
                    },
                    detail: {
                        name: 'sw.product.detail',
                        children: [
                            {
                                name: 'sw.product.detail.base',
                            },
                        ],
                    },
                },
            },
        },
    },
    {
        name: 'sw.product.detail.base',
        path: '/sw/product/detail/:id/base',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'sw-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: {
                    index: { name: 'sw.product.index' },
                    create: {
                        children: [
                            {
                                name: 'sw.product.create.base',
                            },
                        ],
                        name: 'sw.product.create',
                    },
                    detail: {
                        name: 'sw.product.detail',
                        children: [
                            {
                                name: 'sw.product.detail.base',
                            },
                        ],
                    },
                },
            },
        },
    },
];

const router = createRouter({
    routes,
    history: createWebHashHistory(),
});

async function createWrapper({ checkShopId = jest.fn(() => Promise.resolve()) } = {}) {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    await router.push({ name: 'sw.dashboard.index' });

    return mount(await wrapTestComponent('sw-desktop', { sync: true }), {
        global: {
            plugins: [
                router,
            ],
            stubs: {
                'sw-admin-menu': true,
                'router-view': true,
                'sw-app-shop-id-change-modal': true,
                'sw-sidebar-renderer': true,
                'sw-error-boundary': true,
                'sw-settings-services-grant-permissions-modal': true,
                'sw-settings-usage-data-consent-modal': true,
                'sw-settings-usage-data-consent-modal-data-provider': true,
                'sw-request-consent-modal': true,
            },
            provide: {
                shopIdChangeService: {
                    checkShopId,
                },
                userActivityApiService: {
                    increment: jest.fn(() => Promise.resolve()),
                },
            },
        },
    });
}

describe('src/app/component/structure/sw-desktop', () => {
    beforeAll(() => {
        Shopware.Store.get('context').app.config.settings = {
            appsRequireAppUrl: true,
            appUrlReachable: true,
        };
    });

    beforeEach(async () => {
        Shopware.Store.get('session').setCurrentUser({
            id: 'id',
        });

        Shopware.Store.get('context').app.config.settings = {
            appsRequireAppUrl: true,
            appUrlReachable: true,
            enableStagingMode: false,
        };
    });

    it('should be update userConfig when at index route', async () => {
        const wrapper = await createWrapper();

        const onUpdateSearchFrequently = jest.spyOn(wrapper.vm, 'onUpdateSearchFrequently');
        const getModuleMetadata = jest.spyOn(wrapper.vm, 'getModuleMetadata');

        await wrapper.vm.$router.push({ name: 'sw.product.index' });
        await flushPromises();

        expect(onUpdateSearchFrequently).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata.mock.results[0].value).toEqual({
            color: '#57D9A3',
            entity: 'product',
            icon: 'default-symbol-products',
            name: 'product',
            route: { name: 'sw.product.index' },
            title: 'sw-product.general.mainMenuItemGeneral',
        });
    });

    it('should be update userConfig when at create route', async () => {
        const wrapper = await createWrapper();

        const onUpdateSearchFrequently = jest.spyOn(wrapper.vm, 'onUpdateSearchFrequently');
        const getModuleMetadata = jest.spyOn(wrapper.vm, 'getModuleMetadata');

        await wrapper.vm.$router.push({ name: 'sw.product.create.base' });
        await flushPromises();

        expect(onUpdateSearchFrequently).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata.mock.results[0].value).toEqual({
            name: 'product',
            icon: 'default-symbol-products',
            color: '#57D9A3',
            entity: 'product',
            route: { name: 'sw.product.create' },
            action: true,
        });
    });

    it('should be cannot update userConfig when at detail route', async () => {
        const wrapper = await createWrapper();

        const onUpdateSearchFrequently = jest.spyOn(wrapper.vm, 'onUpdateSearchFrequently');
        const getModuleMetadata = jest.spyOn(wrapper.vm, 'getModuleMetadata');

        await router.push({
            name: 'sw.product.detail.base',
            params: { id: 'a34943fe8fe040cd9ce25742a7cf77b2' },
        });

        expect(onUpdateSearchFrequently).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata.mock.results[0].value).toBe(false);
    });

    it('should not call shopIdChangeService when appUrlReachable is false', async () => {
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = false;

        const wrapper = await createWrapper();

        const checkShopIdSpy = jest.spyOn(wrapper.vm.shopIdChangeService, 'checkShopId');

        await wrapper.vm.$router.push({ name: 'sw.product.create.base' });
        await flushPromises();

        expect(checkShopIdSpy).not.toHaveBeenCalled();
    });

    it('should not render consent modal provider while shop ID check is pending', async () => {
        const checkShopId = jest.fn(() => new Promise(() => {}));

        const wrapper = await createWrapper({ checkShopId });

        expect(checkShopId).toHaveBeenCalledTimes(1);
        expect(wrapper.find('sw-settings-usage-data-consent-modal-data-provider-stub').exists()).toBe(false);
    });

    it('should render consent modal provider after shop ID check resolves without changes', async () => {
        const wrapper = await createWrapper({
            checkShopId: jest.fn(() => Promise.resolve(null)),
        });

        await flushPromises();

        expect(wrapper.vm.isShopIdCheckPending).toBe(false);
        expect(wrapper.vm.shopIdCheck).toBeNull();
        expect(wrapper.vm.showUsageDataConsentModalDataProvider).toBe(true);
    });

    it('should not render consent modal provider while shop ID change modal is shown', async () => {
        const wrapper = await createWrapper({
            checkShopId: jest.fn(() => Promise.resolve({ apps: [], fingerprints: {} })),
        });

        await flushPromises();

        expect(wrapper.find('sw-app-shop-id-change-modal-stub').exists()).toBe(true);
        expect(wrapper.find('sw-settings-usage-data-consent-modal-data-provider-stub').exists()).toBe(false);
    });

    it('should show the staging bar, when enabled', async () => {
        Shopware.Store.get('context').app.config.settings.enableStagingMode = true;

        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.sw-staging-bar').exists()).toBeTruthy();
    });

    it('should not show the staging bar, when disabled', async () => {
        Shopware.Store.get('context').app.config.settings.enableStagingMode = false;

        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.sw-staging-bar').exists()).toBeFalsy();
    });
});
