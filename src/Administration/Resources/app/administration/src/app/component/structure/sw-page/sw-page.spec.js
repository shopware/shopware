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

    it('should navigate back to the last visited parent listing with its query after a detour', async () => {
        jest.restoreAllMocks();
        router.addRoute({
            name: 'sw.category.list',
            path: '/sw/category/list',
            component: {},
        });
        router.addRoute({
            name: 'sw.category.detail',
            path: '/sw/category/detail/:id',
            component: {},
        });
        router.addRoute({
            name: 'sw.category.bulk.edit',
            path: '/sw/category/bulk/edit/:id',
            component: {},
        });

        const listWrapper = await createWrapper({
            name: 'sw.category.list',
            path: '/sw/category/list',
            fullPath: '/sw/category/list?term=softshell&page=2',
            meta: {},
        });
        listWrapper.unmount();

        await router.push({ name: 'sw.category.detail', params: { id: '1' } });
        await router.push({ name: 'sw.category.bulk.edit', params: { id: '1' } });
        await router.push({ name: 'sw.category.detail', params: { id: '1' } });

        const wrapper = await createWrapper({
            name: 'sw.category.detail',
            path: '/sw/category/detail/:id',
            fullPath: '/sw/category/detail/1',
            meta: {
                parentPath: 'sw.category.list',
            },
        });

        expect(wrapper.vm.previousRoute).toBe('sw.category.bulk.edit');
        expect(wrapper.vm.routerBack).toBe('/sw/category/list?term=softshell&page=2');
    });

    it('should not reuse a remembered parent route that has route params', async () => {
        jest.restoreAllMocks();
        router.addRoute({
            name: 'sw.group.detail',
            path: '/sw/group/detail/:id?',
            component: {},
        });
        router.addRoute({
            name: 'sw.group.option.detail',
            path: '/sw/group/detail/:groupId/option/:optionId',
            component: {},
        });

        const otherGroupWrapper = await createWrapper({
            name: 'sw.group.detail',
            path: '/sw/group/detail/:id?',
            fullPath: '/sw/group/detail/other-group',
            params: { id: 'other-group' },
            meta: {},
        });
        otherGroupWrapper.unmount();

        await router.push({ name: 'index' });
        await router.push({ name: 'sw.group.option.detail', params: { groupId: 'group', optionId: 'option' } });

        const wrapper = await createWrapper({
            name: 'sw.group.option.detail',
            path: '/sw/group/detail/:groupId/option/:optionId',
            fullPath: '/sw/group/detail/group/option/option',
            params: { groupId: 'group', optionId: 'option' },
            meta: {
                parentPath: 'sw.group.detail',
            },
        });

        expect(wrapper.vm.routerBack).toEqual({ name: 'sw.group.detail' });
    });

    it('should not reuse the listing remembered for another administration user', async () => {
        jest.restoreAllMocks();
        router.addRoute({
            name: 'sw.customer.list',
            path: '/sw/customer/list',
            component: {},
        });
        router.addRoute({
            name: 'sw.customer.detail',
            path: '/sw/customer/detail/:id',
            component: {},
        });

        Shopware.Store.get('session').setCurrentUser({ id: 'first-user' });
        const listWrapper = await createWrapper({
            name: 'sw.customer.list',
            path: '/sw/customer/list',
            fullPath: '/sw/customer/list?term=secret',
            meta: {},
        });
        listWrapper.unmount();

        Shopware.Store.get('session').setCurrentUser({ id: 'second-user' });
        await router.push({ name: 'index' });
        await router.push({ name: 'sw.customer.detail', params: { id: '1' } });

        const wrapper = await createWrapper({
            name: 'sw.customer.detail',
            path: '/sw/customer/detail/:id',
            fullPath: '/sw/customer/detail/1',
            params: { id: '1' },
            meta: {
                parentPath: 'sw.customer.list',
            },
        });

        expect(wrapper.vm.routerBack).toEqual({ name: 'sw.customer.list' });

        Shopware.Store.get('session').setCurrentUser(null);
    });
});
