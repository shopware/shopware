import { config, mount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

const componentWithTabs = {
    name: 'componentWithTabs',
    template: `<div class="component-with-tabs">
        <sw-tabs>
            <template v-for="(route, index) in routes">
                <sw-tabs-item :route="route" :key="index">
                    {{route.name}}
                </sw-tabs-item>
            </template>
        </sw-tabs>
    </div>`,
    props: ['routes']
};

function mountSwTabs(routes) {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();

    localVue.use(VueRouter);

    const router = new VueRouter({
        routes
    });

    return mount(componentWithTabs, {
        localVue,
        router,
        propsData: {
            routes
        },
        stubs: {
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item')
        }
    });
}

describe('sw-tabs', () => {
    it('renders active tab correctly with sub routes', async () => {
        const routes = [{
            name: 'product.base',
            path: '/sw/product/detail/the-id/base'
        }, {
            name: 'product.properties',
            path: '/sw/product/detail/the-id/properties'
        }];

        const wrapper = await mountSwTabs(routes);
        await wrapper.vm.$nextTick();

        wrapper.vm.$router.push({ name: 'product.base' });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        let activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(1);

        let activeTab = activeTabs.at(0);
        expect(activeTab.text()).toEqual('product.base');

        wrapper.vm.$router.push({ name: 'product.properties' });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(1);

        activeTab = activeTabs.at(0);
        expect(activeTab.text()).toEqual('product.properties');

        wrapper.destroy();
    });
    it('renders active tab correctly with sub routes', async () => {
        const routes = [{
            name: 'first.route',
            path: '/starts'
        }, {
            name: 'second.route',
            path: '/starts/with'
        }];

        const wrapper = mountSwTabs(routes);

        wrapper.vm.$router.push({ name: 'first.route' });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        let activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(1);

        let activeTab = activeTabs.at(0);
        expect(activeTab.text()).toEqual('first.route');

        wrapper.vm.$router.push({ name: 'second.route' });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(1);

        activeTab = activeTabs.at(0);
        expect(activeTab.text()).toEqual('second.route');

        wrapper.destroy();
    });

    it('sets active tabs with query parameters', async () => {
        const routes = [{
            name: 'first.route',
            path: '/route/first'
        }];

        const wrapper = mountSwTabs(routes);

        const activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(0);

        wrapper.vm.$router.push({ name: 'first.route', query: { a: 'a', c: 'c' } });
        await wrapper.vm.$nextTick();

        const activeTab = wrapper.find('.sw-tabs-item--active');
        expect(activeTab.text()).toEqual('first.route');

        wrapper.destroy();
    });
});
