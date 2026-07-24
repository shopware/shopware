/**
 * @sw-package discovery
 */
import { createWrapper, registerCmsPageStore } from './fixtures';

describe('src/module/sw-cms/elements/product-listing/config - filters', () => {
    beforeAll(() => {
        registerCmsPageStore();
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper('sorting', { featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-config-product-listing');
        expect(tabs.props('defaultItem')).toBe('content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
            {
                label: 'sw-cms.elements.productListing.config.tab.sorting',
                name: 'sorting',
            },
            {
                label: 'sw-cms.elements.productListing.config.tab.filter',
                name: 'filter',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-product-listing__content-info').exists()).toBe(true);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper('sorting', { featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'sorting');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('sorting');
        expect(wrapper.find('.sw-cms-el-config-product-listing__content-info').exists()).toBe(false);
        expect(wrapper.find('sw-entity-single-select-stub[entity="product_sorting"]').exists()).toBe(true);
    });

    it('should contain content for filter setting', async () => {
        const wrapper = await createWrapper('filter');
        await flushPromises();

        const showFilterManufacturerSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.filter.labelFilterByManufacturer"]',
        );
        const showFilterRatingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.filter.labelFilterByRating"]',
        );
        const showFilterPriceSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.filter.labelFilterByPrice"]',
        );
        const showFilterForFreeShippingSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.filter.labelFilterForFreeShipping"]',
        );

        expect(showFilterManufacturerSwitchField.exists()).toBeTruthy();
        expect(showFilterRatingSwitchField.exists()).toBeTruthy();
        expect(showFilterPriceSwitchField.exists()).toBeTruthy();
        expect(showFilterForFreeShippingSwitchField.exists()).toBeTruthy();
    });

    it('should show use-filter-properties-option when properties available', async () => {
        const wrapper = await createWrapper('filter');

        await wrapper.vm.$nextTick(); // calculate showPropertySelection

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        await wrapper.vm.$nextTick(); // re-render view

        const showUseFilterByPropertiesSwitchField = wrapper.find(
            'input[aria-label="sw-cms.elements.productListing.config.filter.labelUseFilterByProperties"]',
        );
        const showPropertySearchField = wrapper.find(
            'sw-simple-search-field-stub.sw-cms-element-product-listing-config-filter-property-search',
        );
        const showPropertyStatusGrid = wrapper.find('.sw-cms-el-config-product-listing__property-grid');

        expect(showUseFilterByPropertiesSwitchField.exists()).toBeTruthy();
        expect(showPropertySearchField.exists()).toBeTruthy();
        expect(showPropertyStatusGrid.exists()).toBeTruthy();
    });

    it('should sort properties by status', async () => {
        const wrapper = await createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        // enable filterByProperties otherwise any property is active
        wrapper.vm.filterByProperties = true;

        expect(wrapper.vm.showPropertySelection).toBeTruthy();

        const expectedOrderWhenNoPropertiesAreActive = [
            'bar',
            'baz',
            'foo',
        ];
        const propertiesOrderByAPI = wrapper.vm.properties.map((item) => item.name);

        expect(expectedOrderWhenNoPropertiesAreActive).toEqual(propertiesOrderByAPI);

        // eslint-disable-next-line inclusive-language/use-inclusive-words
        wrapper.vm.element.config.propertyWhitelist.value = ['x02']; // activate Property_group 'baz'
        wrapper.vm.loadFilterableProperties();

        await wrapper.vm.$nextTick(); // fetch property_group call

        const expectedOrderWhenPropertyBazIsActive = [
            'baz',
            'bar',
            'foo',
        ];
        const propertiesOrderBySortingViaActiveState = wrapper.vm.properties.map((item) => item.name);

        expect(expectedOrderWhenPropertyBazIsActive).toEqual(propertiesOrderBySortingViaActiveState);
    });

    it('should filter properties by term', async () => {
        const wrapper = await createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call
        await flushPromises();

        const expectedToDisplayProperties = [
            'bar',
            'baz',
            'foo',
        ];
        const displayedProperties = wrapper.vm.properties.map((item) => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        wrapper.vm.filterPropertiesTerm = 'bar';
        wrapper.vm.onFilterProperties();
        await wrapper.vm.$nextTick(); // fetch filtered list
        await flushPromises();

        const expectedToDisplayFilteredProperties = ['bar'];
        const displayedFilteredProperties = wrapper.vm.properties.map((item) => item.name);

        expect(expectedToDisplayFilteredProperties).toEqual(displayedFilteredProperties);

        await wrapper.vm.$nextTick(); // await template re-render
        await flushPromises();

        const emptyStateElement = wrapper.findComponent({
            name: 'sw-empty-state-stub',
        });
        expect(emptyStateElement.exists()).toBe(false);
    });

    it('should show an empty-state when filtered properties have no result', async () => {
        const wrapper = await createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        const expectedToDisplayProperties = [
            'bar',
            'baz',
            'foo',
        ];
        const displayedProperties = wrapper.vm.properties.map((item) => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        wrapper.vm.filterPropertiesTerm = 'notinlist';
        wrapper.vm.onFilterProperties();
        await wrapper.vm.$nextTick(); // fetch filtered list

        const expectedToDisplayFilteredProperties = [];
        const displayedFilteredProperties = wrapper.vm.properties.map((item) => item.name);
        expect(expectedToDisplayFilteredProperties).toEqual(displayedFilteredProperties);

        await wrapper.vm.$nextTick(); // await template re-render

        const emptyStateElement = wrapper.findComponent({
            name: 'sw-empty-state-stub',
        });
        expect(emptyStateElement).toBeTruthy();
    });

    it('should toggle property filters', async () => {
        /* eslint-disable inclusive-language/use-inclusive-words */
        const wrapper = await createWrapper('filter');

        await wrapper.vm.$nextTick(); // fetch property_group call

        const expectedToDisplayProperties = [
            'bar',
            'baz',
            'foo',
        ];
        const displayedProperties = wrapper.vm.properties.map((item) => item.name);
        expect(expectedToDisplayProperties).toEqual(displayedProperties);

        // check initial configuration
        let selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual([]);

        // simulate a click on a switch to select the property foo
        wrapper.vm.propertyStatusChanged(true, 'x03');

        // check that foo with the id x03 got added to the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual(['x03']);

        // simulate a click on a switch to select the property baz
        wrapper.vm.propertyStatusChanged(true, 'x02');

        // check that baz with the id x02 got added to the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual([
            'x03',
            'x02',
        ]);

        // simulate a click on a switch to deselect the property foo
        wrapper.vm.propertyStatusChanged(false, 'x03');

        // check that foo with the id x03 got removed from the selection
        selectedProperties = wrapper.vm.element.config.propertyWhitelist.value;
        expect(selectedProperties).toEqual(['x02']);
        /* eslint-enable inclusive-language/use-inclusive-words */
    });
});
