/**
 * @package buyers-experience
 */
import Vue from 'vue';
import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElConfigProductListing from 'src/module/sw-cms/elements/product-listing/config';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

Shopware.Component.register('sw-cms-el-config-product-listing', swCmsElConfigProductListing);

const productSortingRepositoryMock = {
    search: jest.fn(() => {
        return Promise.resolve(
            new EntityCollection('', '', Shopware.Context.api, null, [{}], 1),
        );
    }),
    route: '/product_sorting',
    schema: {
        entity: 'product_sorting',
    },
};

const propertyGroupMock = [
    { id: 'x01', name: 'bar' },
    { id: 'x02', name: 'baz' },
    { id: 'x03', name: 'foo' },
];

const propertyGroupRepositoryMock = {
    search(criteria) {
        let properties = [...propertyGroupMock];
        if (criteria?.term) {
            properties = properties.filter(propertyGroup => propertyGroup.name.includes(criteria.term));
        }

        return Promise.resolve(properties);
    },
    route: '/property_group',
    schema: {
        entity: 'property_group',
    },
};

const repositoryMockFactory = (entity) => {
    if (entity === 'product_sorting') {
        return productSortingRepositoryMock;
    }

    if (entity === 'property_group') {
        return propertyGroupRepositoryMock;
    }

    throw new Error(`Repository for ${entity} is not implemented`);
};


async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-el-config-product-listing'), {
        stubs: {
            'sw-cms-el-config-product-listing-config-sorting-grid': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
            'sw-simple-search-field': true,
            'sw-entity-multi-select': true,
            'sw-select-field': true,
            'sw-switch-field': true,
            'sw-pagination': true,
            'sw-container': true,
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-alert': true,
            'sw-empty-state': true,
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-icon': true,
            'sw-help-text': true,
        },
        provide: {
            cmsService: {
                getCmsElementRegistry: () => {
                    return [];
                },
            },
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity),
            },

        },
        propsData: Vue.observable({
            defaultConfig: {},
            element: {
                config: {
                    boxLayout: {
                        value: {},
                    },
                    defaultSorting: {
                        value: {},
                    },
                    availableSortings: {
                        value: {},
                    },
                    showSorting: {
                        value: true,
                    },
                    useCustomSorting: {
                        value: true,
                    },
                    filters: {
                        value: 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
                    },
                    // eslint-disable-next-line inclusive-language/use-inclusive-words
                    propertyWhitelist: {
                        value: [],
                    },
                },
            },
        }),
    });
}

describe('src/module/sw-cms/elements/product-listing/config', () => {
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain tab items content, sorting and filter', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-product-listing__tab-content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-config-product-listing__tab-sorting').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-config-product-listing__tab-filter').exists()).toBeTruthy();
    });

    it('should contain content for sorting when defaultSorting is deactivated', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-cms-el-config-product-listing__tab-sorting').trigger('click');

        const showSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]');
        const useDefaultSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]');
        const defaultSortingIdSelect = wrapper.find('.sw-cms-element-config-product-listing__sorting-default-select');
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
        const wrapper = await createWrapper();

        await wrapper.setData({
            element: {
                config: {
                    useCustomSorting: {
                        value: false,
                    },
                },
            },
        });

        await wrapper.find('.sw-cms-el-config-product-listing__tab-sorting').trigger('click');

        const showSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelShowSorting"]');
        const useDefaultSortingSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.sorting.labelUseCustomSortings"]');
        const defaultSortingIdSelect = wrapper.find('.sw-cms-element-config-product-listing__sorting-default-select');
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
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({});

        await wrapper.setData({
            productSortings: [
                {
                    key: 'foo',
                    priority: 2,
                },
                {
                    key: 'bar',
                    priority: 5,
                },
            ],
        });

        expect(wrapper.vm.element.config.availableSortings.value).toStrictEqual({
            foo: 2,
            bar: 5,
        });
    });

    it('should automatically clear defaultSorting if defaultSorting is null', async () => {
        productSortingRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(
                new EntityCollection('', '', Shopware.Context.api, null, [], 0),
            );
        });

        const wrapper = await createWrapper();

        expect(productSortingRepositoryMock.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.defaultSorting).toStrictEqual({});

        await wrapper.find('.sw-cms-el-config-product-listing__tab-sorting').trigger('click');
        expect(wrapper.find('.sw-cms-element-config-product-listing__sorting-default-select').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-element-config-product-listing__sorting-default-select input').element.value).toBe('');
    });

    it('should update the productSortings priority with the values from the config', async () => {
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

    it('should transform the product sortings correctly', async () => {
        const wrapper = await createWrapper();

        const before = [
            {
                key: 'foo',
                priority: 2,
            },
            {
                key: 'bar',
                priority: 5,
            },
        ];

        await wrapper.setData({
            productSortings: before,
        });

        const after = wrapper.vm.transformProductSortings();

        expect(after).toStrictEqual({
            bar: 5,
            foo: 2,
        });
    });

    it('should contain content for filter setting', async () => {
        const wrapper = await createWrapper('filter');

        await wrapper.find('.sw-cms-el-config-product-listing__tab-filter').trigger('click');

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
        const wrapper = await createWrapper('filter');
        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        await wrapper.find('.sw-cms-el-config-product-listing__tab-filter').trigger('click');

        const showUseFilterByPropertiesSwitchField = wrapper
            .find('sw-switch-field-stub[label="sw-cms.elements.productListing.config.filter.labelUseFilterByProperties"]');
        const showPropertySearchField = wrapper
            .find('sw-simple-search-field-stub.sw-cms-element-product-listing-config-filter-property-search');
        const showPropertyStatusGrid = wrapper
            .find('.sw-cms-el-config-product-listing-property-grid');

        expect(showUseFilterByPropertiesSwitchField.exists()).toBeTruthy();
        expect(showPropertySearchField.exists()).toBeTruthy();
        expect(showPropertyStatusGrid.exists()).toBeTruthy();
    });

    it('should sort properties by status', async () => {
        const wrapper = await createWrapper('filter');

        // enable filterByProperties otherwise any property is active
        await wrapper.setData({
            filterByProperties: true,
        });

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        const expectedOrderWhenNoPropertiesAreActive = ['bar', 'baz', 'foo'];
        const propertiesOrderByAPI = wrapper.vm.properties.map(item => item.name);

        expect(expectedOrderWhenNoPropertiesAreActive).toEqual(propertiesOrderByAPI);

        await wrapper.setData({
            element: {
                config: {
                    // eslint-disable-next-line inclusive-language/use-inclusive-words
                    propertyWhitelist: {
                        value: ['x02'],
                    },
                },
            },
        });
        await wrapper.vm.loadFilterableProperties();

        const expectedOrderWhenPropertyBazIsActive = ['baz', 'bar', 'foo'];
        const propertiesOrderBySortingViaActiveState = wrapper.vm.properties.map(item => item.name);

        expect(expectedOrderWhenPropertyBazIsActive).toEqual(propertiesOrderBySortingViaActiveState);
    });

    it('should filter properties by term', async () => {
        const wrapper = await createWrapper('filter');

        const expectedToDisplayProperties = ['bar', 'baz', 'foo'];
        const displayedProperties = wrapper.vm.properties.map(item => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        await wrapper.setData({
            filterPropertiesTerm: 'bar',
        });
        await wrapper.vm.onFilterProperties();

        const expectedToDisplayFilteredProperties = ['bar'];
        const displayedFilteredProperties = wrapper.vm.properties.map(item => item.name);

        expect(expectedToDisplayFilteredProperties).toEqual(displayedFilteredProperties);

        const emptyStateElement = wrapper.findComponent({ name: 'sw-empty-state-stub' });
        expect(emptyStateElement.element).not.toBeTruthy();
    });

    it('should show an empty-state when filtered properties have no result', async () => {
        const wrapper = await createWrapper('filter');

        const expectedToDisplayProperties = ['bar', 'baz', 'foo'];
        const displayedProperties = wrapper.vm.properties.map(item => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        await wrapper.setData({
            filterPropertiesTerm: 'notinlist',
        });
        await wrapper.vm.onFilterProperties();

        const expectedToDisplayFilteredProperties = [];
        const displayedFilteredProperties = wrapper.vm.properties.map(item => item.name);
        expect(expectedToDisplayFilteredProperties).toEqual(displayedFilteredProperties);

        const emptyStateElement = wrapper.findComponent({ name: 'sw-empty-state-stub' });
        expect(emptyStateElement).toBeTruthy();
    });

    it('should toggle property filters', async () => {
        /* eslint-disable inclusive-language/use-inclusive-words */
        const wrapper = await createWrapper('filter');

        const expectedToDisplayProperties = ['bar', 'baz', 'foo'];
        const displayedProperties = wrapper.vm.properties.map(item => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        // check initial configuration
        let selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual([]);

        // simulate a click on a switch to select the property foo
        wrapper.vm.propertyStatusChanged('x03');

        // check that foo with the id x03 got added to the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual(['x03']);

        // simulate a click on a switch to select the property baz
        wrapper.vm.propertyStatusChanged('x02');

        // check that baz with the id x02 got added to the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual(['x03', 'x02']);

        // simulate a click on a switch to deselect the property foo
        wrapper.vm.propertyStatusChanged('x03');

        // check that foo with the id x03 got removed from the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual(['x02']);
        /* eslint-enable inclusive-language/use-inclusive-words */
    });
});
