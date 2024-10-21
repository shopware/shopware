/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createSingleSelect(customOptions) {
    const options = {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div @click="$emit(\'click\', $event)"></div>',
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
                'mt-floating-ui': true,
            },
        },
        props: {
            value: null,
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

    return mount(
        await wrapTestComponent('sw-single-select', {
            sync: true,
        }),
        {
            ...options,
            ...customOptions,
        },
    );
}

describe('components/sw-single-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createSingleSelect();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the result list on click on .sw-select__selection', async () => {
        const wrapper = await createSingleSelect();
        await flushPromises();
        await wrapper.find('.sw-select__selection').trigger('click');

        await flushPromises();

        const resultList = wrapper.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
        expect(wrapper.emitted()).toHaveProperty('on-open-change');
    });

    it('should show the result items', async () => {
        const swSingleSelect = await createSingleSelect();
        await flushPromises();
        await swSingleSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const entryOne = swSingleSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        const entryTwo = swSingleSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        const entryThree = swSingleSelect.find('.sw-select-option--2');
        expect(entryThree.text()).toBe('Entry 3');
    });

    it('should close the result list after clicking an item', async () => {
        const swSingleSelect = await createSingleSelect();
        await flushPromises();

        await swSingleSelect.find('.sw-select__selection').trigger('click');
        await flushPromises();
        await swSingleSelect.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const resultList = swSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeFalsy();
        expect(swSingleSelect.emitted()).toHaveProperty('on-open-change');
    });

    it('should show the label for the selected value property', async () => {
        const swSingleSelect = await createSingleSelect({
            props: {
                value: 'entryOneValue',
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
        await flushPromises();

        const selectedText = swSingleSelect.find('.sw-single-select__selection-text').text();
        expect(selectedText).toBe('Entry 1');
    });

    it('should fill the search term when you enter an input', async () => {
        const swSingleSelect = await createSingleSelect();
        await flushPromises();

        await swSingleSelect.find('.sw-select__selection').trigger('click');

        const searchInput = swSingleSelect.find('.sw-single-select__selection-input');
        await searchInput.setValue('Entry 3');

        expect(swSingleSelect.vm.searchTerm).toBe('Entry 3');
    });

    it('should filter the entries from the search term', async () => {
        const swSingleSelect = await createSingleSelect();
        await flushPromises();

        await swSingleSelect.find('.sw-select__selection').trigger('click');
        await swSingleSelect.setData({ searchTerm: 'Entry 3' });
        swSingleSelect.vm.search();

        expect(swSingleSelect.vm.searchTerm).toBe('Entry 3');
    });

    it('should not show the selected item on first entry', async () => {
        const wrapper = await createSingleSelect();
        await wrapper.setProps({
            value: 'entryThreeValue',
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-option--0').text()).toBe('Entry 1');
        expect(wrapper.find('.sw-select-option--1').text()).toBe('Entry 2');
        expect(wrapper.find('.sw-select-option--2').text()).toBe('Entry 3');
    });

    it('should show the clearable icon in the single select', async () => {
        const wrapper = await createSingleSelect({
            attrs: {
                showClearableButton: true,
            },
        });
        await flushPromises();

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.isVisible()).toBe(true);
    });

    it('should clear the selection when clicking on clear icon', async () => {
        const wrapper = await createSingleSelect({
            props: {
                value: 'entryOneValue',
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
            attrs: {
                showClearableButton: true,
            },
        });
        await flushPromises();

        // expect entryOneValue selected
        let selectionText = wrapper.find('.sw-single-select__selection-text');
        expect(selectionText.text()).toBe('Entry 1');

        // expect no emitted value
        expect(wrapper.emitted('change')).toBeUndefined();

        // click on clear
        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        await clearableIcon.trigger('click');
        await flushPromises();

        // expect emitting resetting value
        const emittedChangeValue = wrapper.emitted('update:value')[0];
        expect(emittedChangeValue).toEqual([undefined]);

        // emulate v-model change
        await wrapper.setProps({
            value: emittedChangeValue[0],
        });
        await flushPromises();

        // expect empty selection
        selectionText = wrapper.find('.sw-single-select__selection-text');
        expect(selectionText.text()).toBe('');
    });
});
