/**
 * @package admin
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

const componentWithTabs = {
    name: 'componentWithTabs',
    template: `<div class="component-with-tabs">
        <sw-tabs positionIdentifier="test" ref="swTabsRef">
            <template v-for="(route, index) in routes" :key="index">
                <sw-tabs-item :route="route" :has-error="route.hasError" :has-warning="route.hasWarning">
                    {{route.name}}
                </sw-tabs-item>
            </template>
        </sw-tabs>
    </div>`,
    props: ['routes'],
};

const router = createRouter({
    routes: [
        {
            name: 'index',
            path: '/',
            component: {},
        },
    ],
    history: createWebHashHistory(),
});

async function mountSwTabs(routes) {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    routes.forEach((route) => {
        router.addRoute(route);
    });

    return mount(componentWithTabs, {
        attachTo: document.body,
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'sw-tabs': await wrapTestComponent('sw-tabs-deprecated'),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-icon': true,
                'sw-extension-component-section': true,
            },
            plugins: [router],
        },
        props: {
            routes,
        },
    });
}

describe('sw-tabs-deprecated', () => {
    beforeEach(() => {
        jest.spyOn(global, 'requestAnimationFrame').mockImplementation((cb) => cb());
    });

    it('renders active tab correctly with sub routes', async () => {
        const routes = [
            {
                name: 'first.route',
                path: '/starts',
                component: {},
            },
            {
                name: 'second.route',
                path: '/starts/with',
                component: {},
            },
        ];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        wrapper.vm.$router.push({ name: 'first.route' });
        await flushPromises();

        let activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        let activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('first.route');

        wrapper.vm.$router.push({ name: 'second.route' });
        await flushPromises();

        activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('second.route');
    });

    it('sets active tabs with query parameters', async () => {
        const routes = [
            {
                name: 'first.route',
                path: '/route/first',
                component: {},
            },
        ];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        const activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(0);

        wrapper.vm.$router.push({
            name: 'first.route',
            query: { a: 'a', c: 'c' },
        });
        await flushPromises();

        const activeTab = wrapper.find('.sw-tabs-item--active');
        expect(activeTab.text()).toBe('first.route');
    });

    it('should have a slider with warning state', async () => {
        const routes = [
            {
                name: 'warning.route',
                path: '/route/warning',
                hasError: false,
                hasWarning: true,
                component: {},
            },
        ];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        wrapper.vm.$router.push({ name: 'warning.route' });
        await flushPromises();

        const slider = wrapper.find('.sw-tabs__slider');
        expect(slider.classes()).toContain('has--warning');
    });

    it('should have a slider with error state', async () => {
        const routes = [
            {
                name: 'error.route',
                path: '/route/error',
                hasError: true,
                hasWarning: false,
                component: {},
            },
            {
                name: 'errorAndWarning.route',
                path: '/route/errorAndWarning',
                hasError: true,
                hasWarning: true,
                component: {},
            },
        ];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        wrapper.vm.$router.push({ name: 'error.route' });
        await flushPromises();

        let slider = wrapper.find('.sw-tabs__slider');
        expect(slider.classes()).toContain('has--error');

        wrapper.vm.$router.push({ name: 'errorAndWarning.route' });
        await flushPromises();

        slider = wrapper.find('.sw-tabs__slider');
        expect(slider.classes()).toContain('has--error');
    });

    it('should register the scrollEventHandler and mutationObserver at mounted', async () => {
        const routes = [
            {
                name: 'first.route',
                path: '/route/first',
                component: {},
            },
        ];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        const swTabs = wrapper.findComponent({ ref: 'swTabsRef' });

        // can't test eventhandler in DOM so we need to access it directly
        expect(swTabs.vm.scrollEventHandler).toBeDefined();
        expect(swTabs.vm.tabContentMutationObserver).toBeDefined();
    });

    it('should call the requestAnimationFrame method on mutation change (directly at start)', async () => {
        const routes = [
            {
                name: 'first.route',
                path: '/route/first',
                component: {},
            },
        ];

        await mountSwTabs(routes);
        await flushPromises();

        expect(global.requestAnimationFrame).toHaveBeenCalled();
    });
});
