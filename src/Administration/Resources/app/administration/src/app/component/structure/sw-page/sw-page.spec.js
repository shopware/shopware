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

async function createWrapper(route = productDetailRoute, props = {}) {
    return mount(await wrapTestComponent('sw-page', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-app-actions': true,
                'sw-help-center': true,
                'sw-help-center-v2': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-app-topbar-button': true,
                'sw-app-topbar-sidebar': true,
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

    it('should render the smart bar back button as a real link and navigate on click', async () => {
        const wrapper = await createWrapper();
        const push = jest.spyOn(router, 'push').mockResolvedValue(undefined);

        const backButton = wrapper.find('.smart-bar__back-btn');
        expect(backButton.element.tagName).toBe('A');
        expect(backButton.attributes('href')).toContain('/sw/product/list');

        await backButton.trigger('click');

        expect(push).toHaveBeenCalled();
    });

    it('should reflect the search bar state as a root class, so the head area rows can react to it', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).toContain('has--search-bar');
        expect(wrapper.find('.sw-page__search-bar').exists()).toBe(true);
        expect(wrapper.find('.sw-page__smart-bar').exists()).toBe(true);
    });

    it('should drop the root class without a search bar while both bars keep rendering', async () => {
        const wrapper = await createWrapper(productDetailRoute, { showSearchBar: false });

        expect(wrapper.classes()).not.toContain('has--search-bar');
        expect(wrapper.find('.sw-page__search-bar').exists()).toBe(false);
        expect(wrapper.find('.sw-page__top-bar-actions').exists()).toBe(true);
        expect(wrapper.find('.sw-page__smart-bar').exists()).toBe(true);
    });
});
