/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/meteor/sw-meteor-single-select', () => {
    async function createWrapper(customConfig = {}) {
        return mount(await wrapTestComponent('sw-meteor-single-select', { sync: true }), {
            props: {
                value: null,
                options: [
                    {
                        label: 'Any',
                        value: null,
                    },
                    {
                        name: 'rating',
                        value: '5',
                        label: 'Min 5 stars',
                    },
                    {
                        name: 'rating',
                        value: '4',
                        label: 'Min 4 stars',
                    },
                    {
                        name: 'rating',
                        value: '3',
                        label: 'Min 3 stars',
                    },
                    {
                        name: 'rating',
                        value: '2',
                        label: 'Min 2 stars',
                    },
                    {
                        name: 'rating',
                        value: '1',
                        label: 'Min 1 star',
                    },
                    {
                        name: 'placeholder',
                        value: 'placeholder1',
                        label: 'Placeholder 1',
                    },
                    {
                        name: 'placeholder',
                        value: 'placeholder2',
                        label: 'Placeholder 2',
                    }],
                label: 'Rating',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-icon': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': true,
                    'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'mt-floating-ui': true,
                    'mt-text-field': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
                provide: {
                    validationService: {},
                },
            },
            ...customConfig,
        });
    }

    beforeAll(() => {
        Shopware.Utils.debounce = function debounce(fn) {
            return function execFunction(...args) {
                fn.apply(this, args);
            };
        };
    });

    it('should show the label with the null value', async () => {
        const wrapper = await createWrapper();

        const label = wrapper.find('.sw-meteor-single-select__label');
        const selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');

        expect(label.text()).toBe('Rating:');
        expect(selectedValueLabel.text()).toBe('Any');
    });

    it('should open the result list on click', async () => {
        const wrapper = await createWrapper();

        let resultList = wrapper.find('.sw-select-result-list');
        expect(resultList.exists()).toBe(false);

        const preview = wrapper.get('.sw-meteor-single-select__preview');
        await preview.trigger('click');
        await flushPromises();

        resultList = wrapper.get('.sw-select-result-list');
        expect(resultList.exists()).toBe(true);
    });

    it('should show search field in result list', async () => {
        const wrapper = await createWrapper();

        let searchField = wrapper.find('.sw-simple-search-field');
        expect(searchField.exists()).toBe(false);

        const preview = wrapper.get('.sw-meteor-single-select__preview');
        await preview.trigger('click');
        await flushPromises();

        searchField = wrapper.get('.sw-simple-search-field');
        expect(searchField.exists()).toBe(true);
    });

    it('should hide search field if less than 7 options are present', async () => {
        const options = [
            { value: '1', label: 'Option 1' },
            { value: '2', label: 'Option 2' },
            { value: '3', label: 'Option 3' },
            { value: '4', label: 'Option 4' },
            { value: '5', label: 'Option 5' },
            { value: '6', label: 'Option 6' },
        ];
        const wrapper = await createWrapper({ propsData: {
            value: null,
            options,
            label: 'Rating',
        } });

        const preview = wrapper.find('.sw-meteor-single-select__preview');
        await preview.trigger('click');

        const searchField = wrapper.find('.sw-simple-search-field');
        expect(searchField.exists()).toBe(false);
    });

    it('should show all options in list', async () => {
        const wrapper = await createWrapper();

        const preview = wrapper.get('.sw-meteor-single-select__preview');
        await preview.trigger('click');
        await flushPromises();

        const results = wrapper.findAll('.sw-select-result');

        expect(results.at(0).text()).toBe('Any');
        expect(results.at(1).text()).toBe('Min 5 stars');
        expect(results.at(2).text()).toBe('Min 4 stars');
        expect(results.at(3).text()).toBe('Min 3 stars');
        expect(results.at(4).text()).toBe('Min 2 stars');
        expect(results.at(5).text()).toBe('Min 1 star');
    });

    it('should emit changed value when clicked on option', async () => {
        const wrapper = await createWrapper();

        const preview = wrapper.get('.sw-meteor-single-select__preview');
        await preview.trigger('click');
        await flushPromises();

        const results = wrapper.findAllComponents('.sw-select-result');

        expect(results.at(3).text()).toBe('Min 3 stars');
        await results.at(3).trigger('click');

        expect(wrapper.emitted('update:value')[0]).toEqual(['3']);
    });

    it('should change the label when value prop changed', async () => {
        const wrapper = await createWrapper();

        let selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');
        expect(selectedValueLabel.text()).toBe('Any');

        await wrapper.setProps({
            value: '3',
        });

        selectedValueLabel = wrapper.find('.sw-meteor-single-select__selected-value-label');
        expect(selectedValueLabel.text()).toBe('Min 3 stars');
    });

    it('should filter options when user searches', async () => {
        jest.useFakeTimers();
        const wrapper = await createWrapper();

        const preview = wrapper.get('.sw-meteor-single-select__preview');
        await preview.trigger('click');
        await flushPromises();

        const searchFieldInput = wrapper.get('.sw-simple-search-field input');
        await searchFieldInput.setValue('stars');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(searchFieldInput.element.value).toBe('stars');

        const results = wrapper.findAll('.sw-select-result');
        expect(results).toHaveLength(4);
        expect(results.at(0).text()).toBe('Min 5 stars');
        expect(results.at(1).text()).toBe('Min 4 stars');
        expect(results.at(2).text()).toBe('Min 3 stars');
        expect(results.at(3).text()).toBe('Min 2 stars');
    });
});
