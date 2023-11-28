/**
 * @package admin
 */

import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-button';

const createMultiSelect = async (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-label': await Shopware.Component.build('sw-label'),
            'sw-button': await Shopware.Component.build('sw-button'),
        },
        propsData: {
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

    return shallowMount(await Shopware.Component.build('sw-multi-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-multi-select', () => {
    it('should be a Vue.js component', async () => {
        const swMultiSelect = await createMultiSelect();

        expect(swMultiSelect.vm).toBeTruthy();
    });

    it('should open the result list on click on .sw-select__selection', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');

        const resultList = swMultiSelect.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
    });

    it('should show the result items', async () => {
        const swMultiSelect = await createMultiSelect();
        await swMultiSelect.find('.sw-select__selection').trigger('click');

        const entryOne = swMultiSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        const entryThree = swMultiSelect.find('.sw-select-option--2');
        expect(entryThree.text()).toBe('Entry 3');
    });

    it('should emit the first option', async () => {
        const changeSpy = jest.fn();

        const swMultiSelect = await createMultiSelect({
            listeners: {
                change: changeSpy,
            },
        });
        await swMultiSelect.find('.sw-select__selection').trigger('click');

        const entryOne = swMultiSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        await entryOne.trigger('click');
        expect(changeSpy).toHaveBeenCalledWith(['entryOneValue']);
    });

    it('should emit the second option', async () => {
        const changeSpy = jest.fn();

        const swMultiSelect = await createMultiSelect({
            listeners: {
                change: changeSpy,
            },
        });
        await swMultiSelect.find('.sw-select__selection').trigger('click');

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        await entryTwo.trigger('click');
        expect(changeSpy).toHaveBeenCalledWith(['entryTwoValue']);
    });

    it('should emit two options', async () => {
        const changeSpy = jest.fn();

        const swMultiSelect = await createMultiSelect({
            listeners: {
                change: changeSpy,
            },
        });

        await swMultiSelect.setProps({
            value: ['entryOneValue'],
        });

        await swMultiSelect.find('.sw-select__selection').trigger('click');

        const entryTwo = swMultiSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        await entryTwo.trigger('click');

        expect(changeSpy).toHaveBeenLastCalledWith(['entryOneValue', 'entryTwoValue']);
    });

    it('should not close the result list after clicking an item', async () => {
        const swMultiSelect = await createMultiSelect();

        await swMultiSelect.find('.sw-select__selection').trigger('click');

        await swMultiSelect.find('.sw-select-option--0').trigger('click');
        await swMultiSelect.setProps({
            value: ['entryOneValue'],
        });

        const resultList = swMultiSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeTruthy();
    });

    it('should show the label for the selected value property', async () => {
        const swMultiSelect = await createMultiSelect({
            propsData: {
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
            propsData: {
                value: ['entryOneValue', 'entryThreeValue'],
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
