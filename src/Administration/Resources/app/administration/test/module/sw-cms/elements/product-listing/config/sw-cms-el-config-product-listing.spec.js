import Vue from 'vue';
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-listing/config';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-el-config-product-listing'), {
        localVue,
        stubs: {
            'sw-tabs': {
                data() {
                    return { active: 'sorting' };
                },
                template: `
<div>
    <slot></slot>
    <slot name="content" v-bind="{ active }"></slot>
</div>`
            },
            'sw-tabs-item': true,
            'sw-select-field': true,
            'sw-alert': true,
            'sw-switch-field': true,
            'sw-entity-single-select': true,
            'sw-entity-multi-select': true,
            'sw-cms-el-config-product-listing-config-sorting-grid': true
        },
        provide: {
            cmsService: {
                getCmsElementRegistry: () => {
                    return [];
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    route: '/product_sorting',
                    schema: {
                        entity: 'product_sorting'
                    }
                })
            },
            feature: {
                isActive: () => true
            }
        },
        mocks: {
            $tc: t => t
        },
        propsData: Vue.observable({
            defaultConfig: {},
            element: {
                config: {
                    boxLayout: {
                        value: {}
                    },
                    defaultSorting: {
                        value: {}
                    },
                    availableSortings: {
                        value: {}
                    },
                    showSorting: {
                        value: true
                    },
                    useCustomSorting: {
                        value: true
                    }
                }
            }
        })
    });
}

describe('src/module/sw-cms/elements/product-listing/config', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain both tab items', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('sw-tabs-item-stub[name="content"]').exists()).toBeTruthy();
        expect(wrapper.find('sw-tabs-item-stub[name="sorting"]').exists()).toBeTruthy();
    });

    it('should contain content for sorting when defaultSorting is deactivated', () => {
        const wrapper = createWrapper();

        const showSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]');
        const useDefaultSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]');
        const defaultSortingIdSelect = wrapper.find('sw-entity-single-select-stub[entity="product_sorting"]');
        const productSortingsSelect = wrapper.find('sw-entity-multi-select-stub');
        const cmsElConfigProductListingConfigSortingGrid = wrapper
            .find('sw-cms-el-config-product-listing-config-sorting-grid-stub');

        expect(showSortingSwitchField.exists()).toBeTruthy();
        expect(useDefaultSortingSwitchField.exists()).toBeTruthy();
        expect(defaultSortingIdSelect.exists()).toBeTruthy();
        expect(productSortingsSelect.exists()).toBeTruthy();
        expect(cmsElConfigProductListingConfigSortingGrid.exists()).toBeTruthy();
    });

    it('should contain only some content for sorting when defaultSorting is activated', async () => {
        const wrapper = createWrapper();
        wrapper.vm.element.config.useCustomSorting.value = false;

        await wrapper.vm.$nextTick();

        const showSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]');
        const useDefaultSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]');
        const defaultSortingIdSelect = wrapper.find('sw-entity-single-select-stub[entity="product_sorting"]');
        const productSortingsSelect = wrapper.find('sw-entity-multi-select-stub');
        const cmsElConfigProductListingConfigSortingGrid = wrapper
            .find('sw-cms-el-config-product-listing-config-sorting-grid-stub');

        expect(showSortingSwitchField.exists()).toBeTruthy();
        expect(useDefaultSortingSwitchField.exists()).toBeTruthy();
        expect(defaultSortingIdSelect.exists()).toBeFalsy();
        expect(productSortingsSelect.exists()).toBeFalsy();
        expect(cmsElConfigProductListingConfigSortingGrid.exists()).toBeFalsy();
    });

    it('should update the config when product sortings changes', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({});

        await wrapper.setData({
            productSortings: [
                {
                    key: 'foo',
                    priority: 2
                },
                {
                    key: 'bar',
                    priority: 5
                }
            ]
        });

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({
            foo: 2,
            bar: 5
        });
    });

    it('should update the productSortings priority with the values from the config', () => {
        const wrapper = createWrapper();

        wrapper.setData({
            element: {
                config: {
                    availableSortings: {
                        value: {
                            foo: 4
                        }
                    }
                }
            }
        });

        const before = [
            {
                key: 'foo',
                value: 'test',
                priority: 7
            }
        ];

        const after = wrapper.vm.updateValuesFromConfig(before);

        expect(after).toStrictEqual([
            {
                key: 'foo',
                value: 'test',
                priority: 4
            }
        ]);
    });

    it('should transform the product sortings corrrectly', () => {
        const wrapper = createWrapper();

        const before = [
            {
                key: 'foo',
                priority: 2
            },
            {
                key: 'bar',
                priority: 5
            }
        ];

        wrapper.setData({
            productSortings: before
        });

        const after = wrapper.vm.transformProductSortings();

        expect(after).toStrictEqual({
            bar: 5,
            foo: 2
        });
    });
});
