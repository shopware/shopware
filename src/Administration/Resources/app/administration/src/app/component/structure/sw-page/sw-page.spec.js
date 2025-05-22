/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import 'src/app/component/structure/sw-page';

const productDetailRoute = {
    name: 'sw.product.detail',
    path: '/sw/product/detail/:id?',
    component: {},
    meta: {
        $module: {
            entity: 'product',
        },
        parentPath: 'sw.product.list',
    },
};

const router = createRouter({
    routes: [
        {
            name: 'index',
            path: '/',
            component: {},
        },
        {
            name: 'sw.product.list',
            path: '/sw/product/list',
            component: {},
            meta: {
                $module: {
                    entity: 'product',
                },
            },
        },
        productDetailRoute,
    ],
    history: createWebHashHistory(),
});

async function createWrapper(route = productDetailRoute) {
    return mount(await wrapTestComponent('sw-page', { sync: true }), {
        global: {
            stubs: {
                'sw-search-bar': true,
                'sw-notification-center': true,
                'router-link': true,
                'sw-app-actions': true,
                'sw-help-center': true,
                'sw-help-center-v2': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-app-topbar-button': true,
            },
            plugins: [router],
            mocks: {
                $route: route,
                $router: router,
            },
        },
    });
}

describe('src/app/component/structure/sw-page', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use the header bottom-color specified with the headerBorderColor prop', async () => {
        const wrapper = await createWrapper({
            meta: {
                $module: {
                    color: 'red',
                },
            },
        });

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toBe('border-bottom-color: red; padding-right: 0px;');

        await wrapper.setProps({ headerBorderColor: 'green' });

        expect(wrapper.get('.sw-page__head-area').attributes('style')).toBe(
            'border-bottom-color: green; padding-right: 0px;',
        );
    });

    it('should preserve previous path with query params and reuse them when navigating back', async () => {
        let wrapper = await createWrapper();

        expect(wrapper.vm.previousPath).toBeNull();
        expect(wrapper.vm.previousRoute).toBeNull();
        expect(wrapper.vm.parentRoute).toBe('sw.product.list');
        expect(wrapper.vm.routerBack).toEqual({ name: 'sw.product.list' });

        await router.push({
            name: 'sw.product.list',
            query: { limit: '50', page: '3' },
        });

        await router.push({
            name: 'sw.product.detail',
            params: { id: '1' },
        });

        wrapper.unmount();
        wrapper = await createWrapper();

        expect(wrapper.vm.previousPath).toBe('/sw/product/list?limit=50&page=3');
        expect(wrapper.vm.previousRoute).toBe('sw.product.list');
        expect(wrapper.vm.parentRoute).toBe('sw.product.list');
        expect(wrapper.vm.routerBack).toBe('/sw/product/list?limit=50&page=3');
    });
});
