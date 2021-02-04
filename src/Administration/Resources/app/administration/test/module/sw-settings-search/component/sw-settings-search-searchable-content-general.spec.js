import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-general';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-searchable-content-general'), {
        localVue,

        mocks: {
            $tc: key => key
        },

        stubs: {
            'sw-empty-state': true,
            'sw-entity-listing': true
        },

        propsData: {
            isEmpty: false
        }
    });
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content-general', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when isEmpty variable is true', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            isEmpty: true
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });
});
