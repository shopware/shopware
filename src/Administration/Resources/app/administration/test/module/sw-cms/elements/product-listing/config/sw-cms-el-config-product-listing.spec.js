import Vue from 'vue';
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-listing/config';


const productSortingRepositoryMock = {
    search: () => Promise.resolve([]),
    route: '/product_sorting',
    schema: {
        entity: 'product_sorting'
    }
};
const propertyGroupRepositoryMock = {
    search: () => Promise.resolve([
        { id: 'x01', name: 'foo' },
        { id: 'x02', name: 'bar' },
        { id: 'x03', name: 'baz' }
    ]),
    route: '/property_group',
    schema: {
        entity: 'property_group'
    }
};

const repositoryMockFactory = (entity) => {
    if (entity === 'product_sorting') {
        return productSortingRepositoryMock;
    }

    if (entity === 'property_group') {
        return propertyGroupRepositoryMock;
    }

    return false;
};


function createWrapper(activeTab = 'sorting') {
    const localVue = createLocalVue();
    localVue.filter('asset', key => key);

    return shallowMount(Shopware.Component.build('sw-cms-el-config-product-listing'), {
        localVue,
        stubs: {
            'sw-tabs': {
                data() {
                    return { active: activeTab };
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
            'sw-container': true,
            'sw-simple-search-field': true,
            'sw-cms-el-config-product-listing-config-sorting-grid': true,
            'sw-cms-el-config-product-listing-config-filter-properties-grid': true
        },
        provide: {
            cmsService: {
                getCmsElementRegistry: () => {
                    return [];
                }
            },
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity)
            }

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
                    },
                    filters: {
                        value: 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter'
                    },
                    propertyWhitelist: {
                        value: []
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

    it('should contain tab items content, sorting and filter', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('sw-tabs-item-stub[name="content"]').exists()).toBeTruthy();
        expect(wrapper.find('sw-tabs-item-stub[name="sorting"]').exists()).toBeTruthy();
        expect(wrapper.find('sw-tabs-item-stub[name="filter"]').exists()).toBeTruthy();
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

    it('should contain content for filter setting', () => {
        const wrapper = createWrapper('filter');

        const showFilterManufacturerSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelFilterByManufacturer"]');
        const showFilterRatingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelFilterByRating"]');
        const showFilterPriceSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelFilterByPrice"]');
        const showFilterForFreeShippingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelFilterForFreeShipping"]');


        expect(showFilterManufacturerSwitchField.exists()).toBeTruthy();
        expect(showFilterRatingSwitchField.exists()).toBeTruthy();
        expect(showFilterPriceSwitchField.exists()).toBeTruthy();
        expect(showFilterForFreeShippingSwitchField.exists()).toBeTruthy();
    });

    it('should show use-filter-properties-option when properties available', async () => {
        const wrapper = createWrapper('filter');

        await wrapper.vm.$nextTick(); // calculate showPropertySelection

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        await wrapper.vm.$nextTick(); // re-render view

        const showUseFilterByPropteriesSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelUseFilterByProperties"]');
        const showPropterySearchField = wrapper
            .find('sw-simple-search-field-stub.sw-cms-element-product-listing-config-filter-property-search');
        const showPropteryStatusGrid = wrapper
            .find('sw-cms-el-config-product-listing-config-filter-properties-grid-stub');

        expect(showUseFilterByPropteriesSwitchField.exists()).toBeTruthy();
        expect(showPropterySearchField.exists()).toBeTruthy();
        expect(showPropteryStatusGrid.exists()).toBeTruthy();
    });

    it('should sort properties by status', async () => {
        const wrapper = createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        // enable filterByProperties otherwise any property is active
        wrapper.vm.filterByProperties = true;

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        const expectedOrderWhenNoPropertiesAreActive = ['foo', 'bar', 'baz'];
        const propertiesOrderByAPI = wrapper.vm.properties.map(item => item.name);

        expect(expectedOrderWhenNoPropertiesAreActive).toEqual(propertiesOrderByAPI);

        wrapper.vm.element.config.propertyWhitelist.value = ['x03']; // activate proptery_group 'baz'
        wrapper.vm.loadFilterableProperties();

        await wrapper.vm.$nextTick(); // fetch property_group call

        const expectedOrderWhenPropertyBazIsActive = ['baz', 'foo', 'bar'];
        const propertiesOrderBySortingViaActiveState = wrapper.vm.properties.map(item => item.name);

        expect(expectedOrderWhenPropertyBazIsActive).toEqual(propertiesOrderBySortingViaActiveState);
    });

    it('should filter properties by term', async () => {
        const wrapper = createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        const expectedToDiplayProperties = ['foo', 'bar', 'baz'];
        const displayedProperties = wrapper.vm.displayedProperties.map(item => item.name);
        expect(expectedToDiplayProperties).toEqual(displayedProperties);

        wrapper.vm.filterPropertiesTerm = 'bar';

        const expectedToDiplayFilteredProperties = ['bar'];
        const displayedFilteredProperties = wrapper.vm.displayedProperties.map(item => item.name);
        expect(expectedToDiplayFilteredProperties).toEqual(displayedFilteredProperties);

        await wrapper.vm.$nextTick(); // await template re-render

        const emptyStateElement = wrapper.find('.sw-cms-element-product-listing-config-filter__empty-state');
        expect(emptyStateElement.element).not.toBeTruthy();
    });

    it('should show an empty-state when filtered properties have no result', async () => {
        const wrapper = createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        const expectedToDiplayProperties = ['foo', 'bar', 'baz'];
        const displayedProperties = wrapper.vm.displayedProperties.map(item => item.name);
        expect(expectedToDiplayProperties).toEqual(displayedProperties);

        wrapper.vm.filterPropertiesTerm = 'notinlist';

        const expectedToDiplayFilteredProperties = [];
        const displayedFilteredProperties = wrapper.vm.displayedProperties.map(item => item.name);
        expect(expectedToDiplayFilteredProperties).toEqual(displayedFilteredProperties);

        await wrapper.vm.$nextTick(); // await template re-render

        const emptyStateElement = wrapper.find('.sw-cms-element-product-listing-config-filter__empty-state');
        expect(emptyStateElement.element).toBeTruthy();
    });
});
