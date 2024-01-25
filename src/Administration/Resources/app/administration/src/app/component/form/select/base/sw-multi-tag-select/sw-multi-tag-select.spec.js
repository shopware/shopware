/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-tag-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';

const selector = {
    multiDataSelect: {
        container: '.sw-select__selection',
        input: '.sw-select-selection-list__input',
        popover: '.sw-select-result-list__content',
    },
};

const createMultiDataSelect = async (customOptions) => {
    const options = {
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-button': true,
            'sw-label': true,
        },
        propsData: {
            value: [],
            disabled: false,
        },
    };

    return shallowMount(await Shopware.Component.build('sw-multi-tag-select'), {
        ...options,
        ...customOptions,
    });
};

const pressKey = async (el, key) => {
    await el.trigger('keydown', {
        key: key,
    });
};

const pressEnter = el => pressKey(el, 'Enter');
const pressEspace = el => pressKey(el, 'Escape');

describe('components/sw-multi-tag-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createMultiDataSelect();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the options popover when the user click on .sw-select__selection', async () => {
        const multiDataSelect = await createMultiDataSelect();
        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const selectOptionsPopover = multiDataSelect.find(selector.multiDataSelect.popover);
        expect(selectOptionsPopover.isVisible()).toBeTruthy();
    });

    it('should focus input when the user click on .sw-select__selection', async () => {
        const wrapper = await createMultiDataSelect();
        const focusSpy = jest.spyOn(wrapper.vm.$refs.selectionList, 'focus');

        wrapper.vm.setDropDown(true);
        expect(focusSpy).toHaveBeenCalled();
    });

    it('should show the select field\'s options popover', async () => {
        const messageAddData = 'global.sw-multi-tag-select.addData';
        const messageEnterValidData = 'global.sw-multi-tag-select.enterValidData';

        const multiDataSelect = await createMultiDataSelect();
        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const selectOptionsPopover = multiDataSelect.find(selector.multiDataSelect.popover);
        expect(selectOptionsPopover.text()).toBe(messageEnterValidData);

        const input = multiDataSelect.find(selector.multiDataSelect.input);
        await input.setValue('anything');

        expect(selectOptionsPopover.text()).toBe(messageAddData);
    });

    it('should add a new item when the user selects one using the enter key', async () => {
        const changeSpy = jest.fn();
        const value = 'a16d4da0-4ba5-4c75-973b-515e23e6498a';

        const multiDataSelect = await createMultiDataSelect({
            listeners: {
                change: changeSpy,
            },
        });

        const input = multiDataSelect.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(multiDataSelect.vm.searchTerm).toBe(value);

        pressEnter(input);

        expect(changeSpy).toHaveBeenCalledWith([value]);
        expect(multiDataSelect.vm.searchTerm).toBe('');
    });

    it('should add a new item when the user selects one using the popover', async () => {
        const changeSpy = jest.fn();
        const value = '5f8c8049-ee9f-4f10-b8b6-5daa9536e0c4';

        const multiDataSelect = await createMultiDataSelect({
            listeners: {
                change: changeSpy,
            },
        });

        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const input = multiDataSelect.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(multiDataSelect.vm.searchTerm).toBe(value);

        const addItemPopover = multiDataSelect.find('.sw-multi-tag-select-valid');
        await addItemPopover.trigger('click');

        expect(changeSpy).toHaveBeenCalledWith([value]);
        expect(multiDataSelect.vm.searchTerm).toBe('');
    });

    it('should set inputIsValid to false, when there\'s no searchTerm given', async () => {
        const value = 'a676344c-c0dd-49e5-8fbb-5f570c27762c';

        const multiDataSelect = await createMultiDataSelect();
        const input = multiDataSelect.find(selector.multiDataSelect.input);

        expect(multiDataSelect.vm.inputIsValid).toBeFalsy();
        expect(multiDataSelect.vm.errorObject).toBeNull();

        await input.setValue(value);

        expect(multiDataSelect.vm.searchTerm).toBe(value);
        expect(multiDataSelect.vm.inputIsValid).toBeTruthy();

        await input.setValue('');

        expect(multiDataSelect.vm.inputIsValid).toBeFalsy();
        expect(multiDataSelect.vm.errorObject).toBeNull();
    });

    it('should return the correct property or fallback-value when getKey is used', async () => {
        const subject = {
            lorem: 'f5534067-8e2e-4091-a49b-4e0f65a5c588',
            ipsum: {
                dolor: {
                    sit: {
                        amet: '95a5ab26-2512-4843-8288-871e593a81f1',
                    },
                },
            },
        };

        const multiDataSelect = await createMultiDataSelect();

        expect(multiDataSelect.vm.getKey(subject, 'lorem', null)).toBe(subject.lorem);
        expect(multiDataSelect.vm.getKey(subject, 'ipsum.dolor.sit.amet', null)).toBe(subject.ipsum.dolor.sit.amet);
        expect(multiDataSelect.vm.getKey(subject, 'lorem.ipsum.dolor.sit.amet', 'Whoops!')).toBe('Whoops!');
    });

    it('should add a new value when the option popover is blurred', async () => {
        const changeSpy = jest.fn();
        const value = 'df8777d8-5969-475e-bbc2-f55a14d49ed7';

        const multiDataSelect = await createMultiDataSelect({
            listeners: {
                change: changeSpy,
            },
        });
        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const input = multiDataSelect.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(multiDataSelect.vm.searchTerm).toBe(value);

        pressEspace(input);

        expect(multiDataSelect.vm.searchTerm).toBe('');
        expect(changeSpy).toHaveBeenCalledWith([value]);
    });

    it('should be disabled correctly', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({ disabled: true });
        expect(multiDataSelect.find('.sw-select').classes()).toContain('is--disabled');

        await multiDataSelect.setProps({ disabled: false });
        expect(multiDataSelect.find('.sw-select').classes()).not.toContain('is--disabled');
    });

    it('should show only five tags of selection list and convert to a object', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
                'Selection6',
            ],
        });

        expect(multiDataSelect.vm.visibleValues).toEqual([
            {
                value: 'Selection1',
            },
            {
                value: 'Selection2',
            },
            {
                value: 'Selection3',
            },
            {
                value: 'Selection4',
            },
            {
                value: 'Selection5',
            },
        ]);
    });

    it('should count invisiable value', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [],
        });

        expect(multiDataSelect.vm.invisibleValueCount).toBe(0);

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
                'Selection6',
            ],
        });

        expect(multiDataSelect.vm.invisibleValueCount).toBe(1);
    });

    it('should not remove the last item when value item is empty', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [],
        });

        multiDataSelect.vm.removeLastItem();

        expect(multiDataSelect.emitted('change')).toBeFalsy();
    });

    it('should expand value first when use keyboard delete last item', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
                'Selection6',
            ],
        });

        multiDataSelect.vm.removeLastItem();

        expect(multiDataSelect.vm.limit).toBe(10);
        expect(multiDataSelect.emitted('change')).toBeFalsy();
    });

    it('should emmited a update value when remove item', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
            ],
        });

        multiDataSelect.vm.$emit('change', ['Selection1', 'Selection2', 'Selection3', 'Selection4']);
        multiDataSelect.vm.remove({ value: 'Selection5' });

        expect(multiDataSelect.emitted('change')).toBeTruthy();
        expect(multiDataSelect.emitted('change')[0]).toEqual([['Selection1', 'Selection2', 'Selection3', 'Selection4']]);
    });

    it('should remove the last item', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
            ],
        });

        multiDataSelect.vm.removeLastItem();

        expect(multiDataSelect.emitted('change')).toBeTruthy();
        expect(multiDataSelect.emitted('change')[0]).toEqual([['Selection1', 'Selection2', 'Selection3', 'Selection4']]);
    });

    it('should expand value limit', async () => {
        const multiDataSelect = await createMultiDataSelect();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
                'Selection6',
            ],
        });

        multiDataSelect.vm.expandValueLimit();

        expect(multiDataSelect.vm.limit).toBe(10);
    });
});
