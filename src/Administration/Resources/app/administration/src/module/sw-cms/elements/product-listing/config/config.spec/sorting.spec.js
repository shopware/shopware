/**
 * @sw-package discovery
 */
import { toRaw } from 'vue';
import { createWrapper, EntityCollection, registerCmsPageStore } from './fixtures';

describe('src/module/sw-cms/elements/product-listing/config - sorting', () => {
    beforeAll(() => {
        registerCmsPageStore();
    });

    it('should contain tab items content, sorting and filter', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-tabs-item-stub[name="content"]').exists()).toBeTruthy();
        expect(wrapper.find('sw-tabs-item-stub[name="sorting"]').exists()).toBeTruthy();
        expect(wrapper.find('sw-tabs-item-stub[name="filter"]').exists()).toBeTruthy();
    });

    it('should contain content for sorting when defaultSorting is deactivated', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            productSortings: [
                {
                    id: 'foo_id',
                    key: 'foo',
                    priority: 2,
                },
            ],
        });

        const showSortingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]',
        );
        const useDefaultSortingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]',
        );
        const defaultSortingIdSelect = wrapper.find('sw-entity-single-select-stub[entity="product_sorting"]');
        const productSortingsSelect = wrapper.find('sw-entity-multi-select-stub');
        const cmsElConfigProductListingConfigSortingGrid = wrapper.find(
            'sw-cms-el-config-product-listing-config-sorting-grid-stub',
        );

        expect(showSortingSwitchField.exists()).toBeTruthy();
        expect(useDefaultSortingSwitchField.exists()).toBeTruthy();
        expect(defaultSortingIdSelect.exists()).toBeTruthy();
        expect(productSortingsSelect.exists()).toBeTruthy();
        expect(cmsElConfigProductListingConfigSortingGrid.exists()).toBeTruthy();
    });

    it('should hide the sorting grid when no product sortings are selected', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            productSortings: [],
        });

        expect(wrapper.find('sw-cms-el-config-product-listing-config-sorting-grid-stub').exists()).toBeFalsy();
    });

    it('should clear product sortings when restoring inheritance without product sortings', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productSortings = new EntityCollection('', '', {}, {}, [{ id: 'local_sorting' }]);
        wrapper.vm.element.config.availableSortings.value = {};

        await wrapper.vm.initProductSorting();

        expect(wrapper.vm.productSortings).toHaveLength(0);
    });

    it('should contain only some content for sorting when defaultSorting is activated', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.element.config.useCustomSorting.value = false;

        await wrapper.vm.$nextTick();

        const showSortingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]',
        );
        const useDefaultSortingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]',
        );
        const defaultSortingIdSelect = wrapper.find('sw-entity-single-select-stub[entity="product_sorting"]');
        const productSortingsSelect = wrapper.find('sw-entity-multi-select-stub');
        const cmsElConfigProductListingConfigSortingGrid = wrapper.find(
            'sw-cms-el-config-product-listing-config-sorting-grid-stub',
        );

        expect(showSortingSwitchField.exists()).toBeTruthy();
        expect(useDefaultSortingSwitchField.exists()).toBeTruthy();
        expect(defaultSortingIdSelect.exists()).toBeFalsy();
        expect(productSortingsSelect.exists()).toBeFalsy();
        expect(cmsElConfigProductListingConfigSortingGrid.exists()).toBeFalsy();
    });

    it('should update the config when product sortings change', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({});

        await wrapper.setData({
            productSortings: [
                {
                    id: 'foo_id',
                    key: 'foo',
                    priority: 2,
                },
                {
                    id: 'bar_id',
                    key: 'bar',
                    priority: 5,
                },
            ],
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({
            foo_id: 2,
            bar_id: 5,
        });
    });

    it('should persist an updated product sorting priority', async () => {
        const wrapper = await createWrapper();
        const productSorting = { id: 'foo_id', priority: 2 };

        wrapper.vm.productSortings = new EntityCollection('', '', {}, {}, [productSorting]);
        await wrapper.vm.$nextTick();

        productSorting.priority = 10;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({ foo_id: 2 });

        wrapper
            .findComponent('sw-cms-el-config-product-listing-config-sorting-grid-stub')
            .vm.$emit('inline-edit-save', productSorting);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({ foo_id: 10 });
    });

    it('should restore the product sorting priority when inline editing is cancelled', async () => {
        const wrapper = await createWrapper();
        const productSorting = { id: 'foo_id', priority: 2 };

        wrapper.vm.productSortings = new EntityCollection('', '', {}, {}, [productSorting]);
        await wrapper.vm.$nextTick();

        productSorting.priority = 10;
        await wrapper.vm.$nextTick();

        wrapper
            .findComponent('sw-cms-el-config-product-listing-config-sorting-grid-stub')
            .vm.$emit('inline-edit-cancel', productSorting);
        await wrapper.vm.$nextTick();

        expect(productSorting.priority).toBe(2);
        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({ foo_id: 2 });
    });

    it('should retain the default sorting when updating product sortings', async () => {
        const wrapper = await createWrapper();
        const defaultSorting = { id: 'default_id', priority: 1 };

        wrapper.vm.productSortings = new EntityCollection('', '', {}, {}, [
            { id: 'foo_id', priority: 2 },
        ]);
        wrapper.vm.defaultSorting = defaultSorting;

        wrapper.vm.onUpdateProductSortings();

        expect([...wrapper.vm.productSortings.getIds()]).toEqual([
            'foo_id',
            'default_id',
        ]);
        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({
            foo_id: 2,
            default_id: 1,
        });
    });

    it('should clear the default sorting when restoring inheritance without a default sorting', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.defaultSorting = { id: 'local_default_sorting' };
        wrapper.vm.defaultSortingId = 'local_default_sorting';
        wrapper.vm.element.config.defaultSorting.value = null;

        await wrapper.vm.restoreDefaultSorting();

        expect(wrapper.vm.defaultSorting).toStrictEqual({});
        expect(wrapper.vm.defaultSortingId).toBeNull();
    });

    it('should clear the default sorting through the select', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.defaultSorting = { id: 'default_sorting' };
        wrapper.vm.defaultSortingId = 'default_sorting';
        await wrapper.vm.$nextTick();

        const defaultSortingSelect = wrapper.findComponent('sw-entity-single-select-stub');
        defaultSortingSelect.vm.$emit('update:value', null);
        defaultSortingSelect.vm.$emit('option-select', 'productSorting', null);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.defaultSorting).toStrictEqual({});
        expect(wrapper.vm.defaultSortingId).toBeNull();
        expect(wrapper.vm.element.config.defaultSorting.value).toBe('');
    });

    it('should update the product sortings priority with the values from the config', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            element: {
                config: {
                    availableSortings: {
                        value: {
                            foo: 4,
                        },
                    },
                },
            },
        });

        const before = [
            {
                key: 'fist',
                value: 'bump',
                priority: 7,
            },
        ];

        const after = wrapper.vm.updateValuesFromConfig(before);

        expect(after).toStrictEqual([
            {
                key: 'fist',
                value: 'bump',
                priority: 7,
            },
        ]);
    });

    it('should assign a new object to availableSortings.value (not mutate) to trigger Vue reactivity', async () => {
        const wrapper = await createWrapper();

        const originalRaw = toRaw(wrapper.vm.element.config.availableSortings.value);

        await wrapper.setData({
            productSortings: [{ id: 'foo_id', key: 'foo', priority: 2 }],
        });
        await wrapper.vm.$nextTick();

        expect(toRaw(wrapper.vm.element.config.availableSortings.value)).not.toBe(originalRaw);
        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({ foo_id: 2 });
    });

    it('should transform the product sortings correctly', async () => {
        const wrapper = await createWrapper();

        const before = [
            {
                id: 'foo_id',
                key: 'foo',
                priority: 2,
            },
            {
                id: 'bar_id',
                key: 'bar',
                priority: 5,
            },
        ];

        await wrapper.setData({
            productSortings: before,
        });

        const after = wrapper.vm.transformProductSortings();

        expect(after).toStrictEqual({
            bar_id: 5,
            foo_id: 2,
        });
    });
});
