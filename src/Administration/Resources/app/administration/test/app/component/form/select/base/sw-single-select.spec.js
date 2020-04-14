import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';

const createSingleSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-icon': '<div></div>',
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text')
        },
        mocks: { $tc: key => key },
        propsData: {
            value: null,
            options: [
                {
                    label: 'Entry 1',
                    value: 'entryOneValue'
                },
                {
                    label: 'Entry 2',
                    value: 'entryTwoValue'
                },
                {
                    label: 'Entry 3',
                    value: 'entryThreeValue'
                }
            ]
        }
    };

    return shallowMount(Shopware.Component.build('sw-single-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-single-select', () => {
    it('should be a Vue.js component', () => {
        const swSingleSelect = createSingleSelect();

        expect(swSingleSelect.isVueInstance()).toBeTruthy();
    });

    it('should open the result list on click on .sw-select__selection', () => {
        const swSingleSelect = createSingleSelect();
        swSingleSelect.find('.sw-select__selection').trigger('click');

        const resultList = swSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
    });

    it('should show the result items', () => {
        const swSingleSelect = createSingleSelect();
        swSingleSelect.find('.sw-select__selection').trigger('click');

        const entryOne = swSingleSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        const entryTwo = swSingleSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        const entryThree = swSingleSelect.find('.sw-select-option--2');
        expect(entryThree.text()).toBe('Entry 3');
    });

    it('should emit the first option', () => {
        const changeSpy = jest.fn();

        const swSingleSelect = createSingleSelect({
            listeners: {
                change: changeSpy
            }
        });
        swSingleSelect.find('.sw-select__selection').trigger('click');

        const entryOne = swSingleSelect.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Entry 1');

        entryOne.trigger('click');
        expect(changeSpy).toHaveBeenCalledWith('entryOneValue');
    });

    it('should emit the second option', () => {
        const changeSpy = jest.fn();

        const swSingleSelect = createSingleSelect({
            listeners: {
                change: changeSpy
            }
        });
        swSingleSelect.find('.sw-select__selection').trigger('click');

        const entryTwo = swSingleSelect.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Entry 2');

        entryTwo.trigger('click');
        expect(changeSpy).toHaveBeenCalledWith('entryTwoValue');
    });

    it('should close the result list after clicking an item', () => {
        const swSingleSelect = createSingleSelect();

        swSingleSelect.find('.sw-select__selection').trigger('click');
        swSingleSelect.find('.sw-select-option--0').trigger('click');

        const resultList = swSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeFalsy();
    });

    it('should show the label for the selected value property', () => {
        const swSingleSelect = createSingleSelect({
            propsData: {
                value: 'entryOneValue',
                options: [
                    {
                        label: 'Entry 1',
                        value: 'entryOneValue'
                    },
                    {
                        label: 'Entry 2',
                        value: 'entryTwoValue'
                    },
                    {
                        label: 'Entry 3',
                        value: 'entryThreeValue'
                    }
                ]
            }
        });

        const selectedText = swSingleSelect.find('.sw-single-select__selection-text').text();
        expect(selectedText).toBe('Entry 1');
    });

    it('should fill the search term when you enter an input', () => {
        const swSingleSelect = createSingleSelect();

        swSingleSelect.find('.sw-select__selection').trigger('click');

        const searchInput = swSingleSelect.find('.sw-single-select__selection-input');
        searchInput.setValue('Entry 3');

        expect(swSingleSelect.vm.searchTerm).toBe('Entry 3');
    });

    it('should filter the entries from the search term', () => {
        const swSingleSelect = createSingleSelect();

        swSingleSelect.find('.sw-select__selection').trigger('click');
        swSingleSelect.setData({ searchTerm: 'Entry 3' });
        swSingleSelect.vm.search();

        expect(swSingleSelect.vm.searchTerm).toBe('Entry 3');
    });
});
