import { mount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

const componentWithTabs = {
    name: 'componentWithTabs',
    template:

`
<div class="component-with-tabs">
    <sw-tabs>
        <template v-for="(route, index) in routes">
            <sw-tabs-item :key="index" :route="route">{{route.name}}</sw-tabs-item>
        </template>
    </sw-tabs>
</div>
`,
    props: ['routes']
};

function mountSwTabs(routes) {
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
        },
        mocks: {
            $device: {
                onResize() {}
            }
        }
    });
}

describe('sw-tabs', () => {
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

        let activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs.length).toBe(1);

        let activeTab = activeTabs.at(0);
        expect(activeTab.text()).toEqual('first.route');

        wrapper.vm.$router.push({ name: 'second.route' });
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
