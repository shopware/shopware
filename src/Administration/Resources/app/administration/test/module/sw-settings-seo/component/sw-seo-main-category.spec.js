import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-settings-seo/component/sw-seo-main-category';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-seo-main-category'), {
        localVue,
        stubs: {
            'sw-single-select': true
        },
        propsData: {
            mainCategories: [],
            categories: []
        }
    });
}

describe('src/module/sw-settings-seo/component/sw-seo-main-category', () => {
    it('should not display main category label', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            overwriteLabel: true
        });

        await wrapper.vm.$nextTick();

        const singleSelect = wrapper.find('sw-single-select-stub');
        expect(singleSelect).toBeDefined();
        expect(singleSelect.attributes('label')).toBeUndefined();
    });

    it('should display main category label', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const singleSelect = wrapper.find('sw-single-select-stub');
        expect(singleSelect).toBeDefined();
        expect(singleSelect.attributes('label')).toBe('sw-seo-url.labelMainCategory');
    });
});
