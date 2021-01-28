import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/meteor/sw-meteor-single-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/base/sw-simple-search-field';

const swMeteorSingleSelect = Shopware.Component.build('sw-meteor-single-select');
function createWrapper(customConfig = {}) {
    return shallowMount(swMeteorSingleSelect, {
        propsData: {
            value: null,
            options: [
                {
                    label: 'Any',
                    value: null
                },
                {
                    name: 'rating',
                    value: '5',
                    label: 'Min 5 stars'
                },
                {
                    name: 'rating',
                    value: '4',
                    label: 'Min 4 stars'
                },
                {
                    name: 'rating',
                    value: '3',
                    label: 'Min 3 stars'
                },
                {
                    name: 'rating',
                    value: '2',
                    label: 'Min 2 stars'
                },
                {
                    name: 'rating',
                    value: '1',
                    label: 'Min 1 star'
                }],
            label: 'Rating'
        },
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-icon': true,
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': true,
            'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text')
        },
        provide: {
            validationService: {}
        },
        ...customConfig
    });
}

describe('src/app/component/meteor/sw-meteor-single-select', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {
        Shopware.Utils.debounce = function debounce(fn) {
            return function execFunction(...args) {
                fn.apply(this, args);
            };
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the label with the null value', async () => {
        const label = wrapper.find('.sw-meteor-single-select__label');
        const selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');

        expect(label.text()).toEqual('Rating:');
        expect(selectedValueLabel.text()).toEqual('Any');
    });

    it('should open the result list on click', async () => {
        let resultList = wrapper.find('.sw-select-result-list');
        expect(resultList.exists()).toBe(false);

        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        resultList = wrapper.find('.sw-select-result-list');
        expect(resultList.exists()).toBe(true);
    });

    it('should show search field in result list', async () => {
        let searchField = wrapper.find('.sw-simple-search-field');
        expect(searchField.exists()).toBe(false);

        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        searchField = wrapper.find('.sw-simple-search-field');
        expect(searchField.exists()).toBe(true);
    });

    it('should show all options in list', async () => {
        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        const results = wrapper.findAll('.sw-select-result');

        expect(results.at(0).text()).toEqual('Any');
        expect(results.at(1).text()).toEqual('Min 5 stars');
        expect(results.at(2).text()).toEqual('Min 4 stars');
        expect(results.at(3).text()).toEqual('Min 3 stars');
        expect(results.at(4).text()).toEqual('Min 2 stars');
        expect(results.at(5).text()).toEqual('Min 1 star');
    });

    it('should emit changed value when clicked on option', async () => {
        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        const results = wrapper.findAll('.sw-select-result');

        expect(results.at(3).text()).toEqual('Min 3 stars');
        await results.at(3).trigger('click');

        expect(wrapper.emitted('change')[0]).toEqual(['3']);
    });

    it('should change the label when value prop changed', async () => {
        let selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');
        expect(selectedValueLabel.text()).toEqual('Any');

        await wrapper.setProps({
            value: '3'
        });

        selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');
        expect(selectedValueLabel.text()).toEqual('Min 3 stars');
    });

    it('should filter options when user searches', async () => {
        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        const searchFieldInput = wrapper.find('.sw-simple-search-field input');
        await searchFieldInput.setValue('stars');

        // Flush debounce
        const debouncedSearch = swMeteorSingleSelect.methods.debouncedSearch;
        await debouncedSearch.flush();

        expect(searchFieldInput.element.value).toEqual('stars');

        const results = wrapper.findAll('.sw-select-result');
        expect(results.length).toBe(4);
        expect(results.at(0).text()).toEqual('Min 5 stars');
        expect(results.at(1).text()).toEqual('Min 4 stars');
        expect(results.at(2).text()).toEqual('Min 3 stars');
        expect(results.at(3).text()).toEqual('Min 2 stars');
    });
});
