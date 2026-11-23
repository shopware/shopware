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

        await flushPromises();

        const singleSelect = wrapper.find('sw-single-select-stub');
        expect(singleSelect).toBeDefined();
        expect(singleSelect.attributes('label')).toBeUndefined();
    });

    it('should display main category label', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
            categories: [{ id: 'categoryId1', translated: { name: 'Category 1' } }],
            currentSalesChannelId: 'salesChannelId1',
        });
        await flushPromises();

        expect(wrapper.vm.mainCategoryForSalesChannel).toEqual(mainCategory);

        await wrapper.vm.onMainCategorySelected(null);
        await flushPromises();

        expect(wrapper.emitted('main-category-remove')).toBeTruthy();
        expect(wrapper.emitted('main-category-remove')[0]).toEqual([mainCategory]);
    });

    describe('category labels', () => {
        const categories = [
            {
                id: 'categoryId1',
                translated: { name: 'Shirts', breadcrumb: ['Clothing', 'Men', 'Shirts'] },
                breadcrumb: ['Clothing', 'Men', 'Shirts'],
            },
            {
                id: 'categoryId2',
                translated: { name: 'Shirts', breadcrumb: ['Clothing', 'Women', 'Shirts'] },
                breadcrumb: ['Clothing', 'Women', 'Shirts'],
            },
            { id: 'categoryId3', translated: { name: 'Sale' }, breadcrumb: null },
        ];

        async function createWrapperWithSlots() {
            return mount(
                await wrapTestComponent('sw-seo-main-category', {
                    sync: true,
                }),
                {
                    global: {
                        stubs: {
                            'sw-single-select': {
                                props: [
                                    'options',
                                    'labelProperty',
                                    'valueProperty',
                                    'value',
                                ],
                                template: `
                                    <div class="sw-single-select-stub">
                                        <span
                                            v-for="option in options"
                                            :key="option[valueProperty]"
                                            class="result-label"
                                        >{{ option[labelProperty] }}</span>
                                        <span class="selected-value">{{ value }}</span>
                                    </div>
                                `,
                            },
                        },
                    },
                    propsData: {
                        mainCategories: [],
                        categories,
                    },
                },
            );
        }

        it('should show the full breadcrumb path to tell categories with the same name apart', async () => {
            const wrapper = await createWrapperWithSlots();
            await flushPromises();

            const labels = wrapper.findAll('.result-label').map((label) => label.text());

            expect(labels).toEqual([
                'Clothing / Men / Shirts',
                'Clothing / Women / Shirts',
                'Sale',
            ]);
        });

        it('should keep the category id as value of the options', async () => {
            const wrapper = await createWrapperWithSlots();
            await wrapper.setProps({
                mainCategories: [{ salesChannelId: 'salesChannelId1', categoryId: 'categoryId2' }],
                currentSalesChannelId: 'salesChannelId1',
            });
            await flushPromises();

            expect(wrapper.find('.selected-value').text()).toBe('categoryId2');
        });

        it('should fall back to the category name without breadcrumb', async () => {
            const wrapper = await createWrapperWithSlots();
            await wrapper.setProps({
                categories: [
                    { id: 'categoryId4', translated: { name: 'Outlet' }, breadcrumb: [] },
                    { id: 'categoryId5', translated: { name: 'Sale' } },
                ],
            });
            await flushPromises();

            const labels = wrapper.findAll('.result-label').map((label) => label.text());

            expect(labels).toEqual(['Outlet', 'Sale']);
        });
    });
});
