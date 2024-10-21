/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const createMultiSelect = async (customOptions) => {
    const options = {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'mt-floating-ui': true,
                'sw-color-badge': true,
            },
        },
        props: {
            value: [],
            options: [
                {
                    label: 'Entry 1',
                    value: 'entryOneValue',
                },
                {
                    label: 'Entry 2',
                    value: 'entryTwoValue',
                },
                {
                    label: 'Entry 3',
                    value: 'entryThreeValue',
                },
            ],
        },
    };

    const wrapper = mount(
        await wrapTestComponent('sw-multi-select', {
            sync: true,
        }),
        {
            ...options,
            ...customOptions,
        },
    );

    await flushPromises();

    return wrapper;
};

describe('components/sw-multi-select', () => {
    it('should be a Vue.js component', async () => {
        const swMultiSelect = await createMultiSelect();

        expect(swMultiSelect.vm).toBeTruthy();
    });

    it('should open the result list on click on .sw-select__selection', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const resultList = swMultiSelect.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
    });

    it('should show the result items', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const entryOne = swMultiSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        const entryThree = swMultiSelect.find('.sw-select-option--2');
        expect(entryThree.text()).toBe('Entry 3');
    });

    it('should emit the first option', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const entryOne = swMultiSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        await entryOne.trigger('click');
        await flushPromises();
        expect(swMultiSelect.emitted('update:value')).toEqual([
            [['entryOneValue']],
        ]);
    });

    it('should emit the second option', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        await entryTwo.trigger('click');
        await flushPromises();
        expect(swMultiSelect.emitted('update:value')).toEqual([
            [['entryTwoValue']],
        ]);
    });

    it('should emit two options', async () => {
        const swMultiSelect = await createMultiSelect();
        await flushPromises();

        await swMultiSelect.setProps({
            value: ['entryOneValue'],
        });
        await flushPromises();

        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        await entryTwo.trigger('click');
        await flushPromises();

        expect(swMultiSelect.emitted('update:value')).toEqual([
            [
                [
                    'entryOneValue',
                    'entryTwoValue',
                ],
            ],
        ]);
    });

    it('should not close the result list after clicking an item', async () => {
        const swMultiSelect = await createMultiSelect();

        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await swMultiSelect.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        await swMultiSelect.setProps({
            value: ['entryOneValue'],
        });
        await flushPromises();

        const resultList = swMultiSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeTruthy();
    });

    it('should show the label for the selected value property', async () => {
        const swMultiSelect = await createMultiSelect({
            props: {
                value: ['entryOneValue'],
                options: [
                    {
                        label: 'Entry 1',
                        value: 'entryOneValue',
                    },
                    {
                        label: 'Entry 2',
                        value: 'entryTwoValue',
                    },
                    {
                        label: 'Entry 3',
                        value: 'entryThreeValue',
                    },
                ],
            },
        });

        const selectedText = swMultiSelect.find('.sw-select-selection-list__item-holder--0').text();
        expect(selectedText).toBe('Entry 1');
    });

    it('should show multiple labels for the selected values properties', async () => {
        const swMultiSelect = await createMultiSelect({
            props: {
                value: [
                    'entryOneValue',
                    'entryThreeValue',
                ],
                options: [
                    {
                        label: 'Entry 1',
                        value: 'entryOneValue',
                    },
                    {
                        label: 'Entry 2',
                        value: 'entryTwoValue',
                    },
                    {
                        label: 'Entry 3',
                        value: 'entryThreeValue',
                    },
                ],
            },
        });

        const selectedTextOne = swMultiSelect.find('.sw-select-selection-list__item-holder--0').text();
        const selectedTextTwo = swMultiSelect.find('.sw-select-selection-list__item-holder--1').text();
        expect(selectedTextOne).toBe('Entry 1');
        expect(selectedTextTwo).toBe('Entry 3');
    });

    it('should save the filled searchTerm', async () => {
        const swMultiSelect = await createMultiSelect();

        await swMultiSelect.find('.sw-select__selection').trigger('click');
        await swMultiSelect.setData({ searchTerm: 'Entry 3' });

        expect(swMultiSelect.vm.searchTerm).toBe('Entry 3');
    });
});
