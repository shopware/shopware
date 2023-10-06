/**
 * @package admin
 */

import { shallowMount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/app/component/structure/sw-desktop';

const routes = [{
    name: 'sw.product.index',
    path: '/sw/product/index',
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
}, {
    name: 'sw.product.create.base',
    path: '/sw/product/create/base',
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
                    children: [{
                        name: 'sw.product.create.base',
                    }],
                    name: 'sw.product.create',
                },
                detail: {
                    name: 'sw.product.detail',
                    children: [{
                        name: 'sw.product.detail.base',
                    }],
                },
            },
        },
    },
}, {
    name: 'sw.product.detail.base',
    path: '/sw/product/detail/a34943fe8fe040cd9ce25742a7cf77b2/base',
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
                    children: [{
                        name: 'sw.product.create.base',
                    }],
                    name: 'sw.product.create',
                },
                detail: {
                    name: 'sw.product.detail',
                    children: [{
                        name: 'sw.product.detail.base',
                    }],
                },
            },
        },
    },
}];

async function createWrapper() {
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const router = new VueRouter({
        routes,
    });

    return shallowMount(await Shopware.Component.build('sw-desktop'), {
        localVue,
        router,
        stubs: {
            'sw-admin-menu': true,
            'router-view': true,
            'sw-app-app-url-changed-modal': true,
            'sw-error-boundary': true,
        },
        provide: {
            appUrlChangeService: {
                getUrlDiff: jest.fn(() => Promise.resolve()),
            },
            userActivityApiService: {
                increment: jest.fn(() => Promise.resolve()),
            },
        },
    });
}

describe('src/app/component/structure/sw-desktop', () => {
    beforeAll(() => {
        Shopware.State.get('context').app.config.settings = {
            appsRequireAppUrl: true,
            appUrlReachable: true,
        };
    });

    beforeEach(async () => {
        Shopware.State.get('session').currentUser = {
            id: 'id',
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
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

        await wrapper.vm.$router.push({
            name: 'sw.product.detail.base',
            params: { id: 'a34943fe8fe040cd9ce25742a7cf77b2' },
        });

        expect(onUpdateSearchFrequently).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata).toHaveBeenCalledTimes(1);
        expect(getModuleMetadata.mock.results[0].value).toBe(false);
    });

    it('should call not urlDiffService when appUrlReachable is false', async () => {
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = false;

        const wrapper = await createWrapper();

        const urlDiffSpy = jest.spyOn(wrapper.vm.appUrlChangeService, 'getUrlDiff');

        await wrapper.vm.$router.push({ name: 'sw.product.create.base' });
        await flushPromises();

        expect(urlDiffSpy).not.toHaveBeenCalled();
    });
});
