/**
 * @sw-package inventory
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

    it('should emit main-category-remove when selection is cleared', async () => {
        const wrapper = await createWrapper();

        const mainCategory = {
            salesChannelId: 'salesChannelId1',
            categoryId: 'categoryId1',
            category: { id: 'categoryId1', translated: { name: 'Category 1' } },
        };

        await wrapper.setProps({
            mainCategories: [mainCategory],
            categories: [
                { id: 'categoryId1', translated: { name: 'Category 1' } },
            ],
            currentSalesChannelId: 'salesChannelId1',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.mainCategoryForSalesChannel).toEqual(mainCategory);

        await wrapper.vm.onMainCategorySelected(null);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('main-category-remove')).toBeTruthy();
        expect(wrapper.emitted('main-category-remove')[0]).toEqual([mainCategory]);
    });
});
