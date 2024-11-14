/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-seo-main-category', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-single-select': true,
                },
            },
            propsData: {
                mainCategories: [],
                categories: [],
            },
        },
    );
}

describe('src/module/sw-settings-seo/component/sw-seo-main-category', () => {
    it('should not display main category label', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            overwriteLabel: true,
        });

        await wrapper.vm.$nextTick();

        const singleSelect = wrapper.find('sw-single-select-stub');
        expect(singleSelect).toBeDefined();
        expect(singleSelect.attributes('label')).toBeUndefined();
    });

    it('should display main category label', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const singleSelect = wrapper.find('sw-single-select-stub');
        expect(singleSelect).toBeDefined();
        expect(singleSelect.attributes('label')).toBe('sw-seo-url.labelMainCategory');
    });
});
