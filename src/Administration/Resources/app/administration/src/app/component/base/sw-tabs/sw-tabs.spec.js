/**
 * @package admin
 */

import { config, mount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

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
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();

    localVue.use(VueRouter);

    const router = new VueRouter({
        routes,
    });

    return mount(componentWithTabs, {
        localVue,
        router,
        propsData: {
            routes,
        },
        stubs: {
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),

            'sw-icon': true,
        },
        attachTo: document.body,
    });
}

describe('sw-tabs', () => {
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
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        let activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        let activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('first.route');

        wrapper.vm.$router.push({ name: 'second.route' });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('second.route');

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
    });
});
