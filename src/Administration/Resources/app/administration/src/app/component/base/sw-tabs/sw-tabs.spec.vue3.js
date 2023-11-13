/**
 * @package admin
 */

import { mount, config } from '@vue/test-utils_v3';
import { createRouter, createWebHashHistory } from 'vue-router_v3';

const componentWithTabs = {
    name: 'componentWithTabs',
    template: `<div class="component-with-tabs">
        <sw-tabs positionIdentifier="test">
            <template v-for="(route, index) in routes">
                <sw-tabs-item :route="route" :key="index" :has-error="route.hasError" :has-warning="route.hasWarning">
                    {{route.name}}
                </sw-tabs-item>
            </template>
        </sw-tabs>
    </div>`,
    props: ['routes'],
};

async function mountSwTabs(routes) {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(componentWithTabs, {
        attachTo: document.body,
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),

                'sw-icon': true,
            },
            plugins: [router],
        },
        props: {
            routes,
        },
    });
}

describe('sw-tabs', () => {
    beforeEach(() => {
        jest.spyOn(global, 'requestAnimationFrame').mockImplementation(cb => cb());
    });

    it('renders active tab correctly with sub routes', async () => {
        const routes = [{
            name: 'first.route',
            path: '/starts',
        }, {
            name: 'second.route',
            path: '/starts/with',
        }];

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
        const routes = [{
            name: 'first.route',
            path: '/route/first',
        }];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        const activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(0);

        wrapper.vm.$router.push({ name: 'first.route', query: { a: 'a', c: 'c' } });
        await flushPromises();

        const activeTab = wrapper.find('.sw-tabs-item--active');
        expect(activeTab.text()).toBe('first.route');
    });

    it('should have a slider with warning state', async () => {
        const routes = [{
            name: 'warning.route',
            path: '/route/warning',
            hasError: false,
            hasWarning: true,
        }];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        wrapper.vm.$router.push({ name: 'warning.route' });
        await flushPromises();

        const slider = wrapper.find('.sw-tabs__slider');
        expect(slider.classes()).toContain('has--warning');
    });

    it('should have a slider with error state', async () => {
        const routes = [{
            name: 'error.route',
            path: '/route/error',
            hasError: true,
            hasWarning: false,
        }, {
            name: 'errorAndWarning.route',
            path: '/route/errorAndWarning',
            hasError: true,
            hasWarning: true,
        }];

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
        const routes = [{
            name: 'first.route',
            path: '/route/first',
        }];

        const wrapper = await mountSwTabs(routes);
        await flushPromises();

        // can't test eventhandler in DOM so we need to access it directly
        expect(wrapper.vm.$children[0].scrollEventHandler).toBeDefined();
        expect(wrapper.vm.$children[0].tabContentMutationObserver).toBeDefined();
    });

    it('should call the requestAnimationFrame method on mutation change (directly at start)', async () => {
        const routes = [{
            name: 'first.route',
            path: '/route/first',
        }];

        await mountSwTabs(routes);
        await flushPromises();

        expect(global.requestAnimationFrame).toHaveBeenCalled();
    });
});
