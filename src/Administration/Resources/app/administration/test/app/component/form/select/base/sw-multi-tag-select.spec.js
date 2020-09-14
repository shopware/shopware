import { createLocalVue, shallowMount } from '@vue/test-utils';
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
        popover: '.sw-select-result-list__content'
    }
};

const createMultiDataSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-icon': {
                template: '<div></div>'
            }
        },
        mocks: { $tc: key => key },
        propsData: {
            value: []
        }
    };

    return shallowMount(Shopware.Component.build('sw-multi-tag-select'), {
        ...options,
        ...customOptions
    });
};

const pressKey = (el, key) => {
    el.trigger('keydown', {
        key: key
    });
};

const pressEnter = el => pressKey(el, 'Enter');
const pressEspace = el => pressKey(el, 'Escape');

describe('components/sw-multi-tag-select', () => {
    it('should be a Vue.js component', async () => {
        expect(createMultiDataSelect().vm).toBeTruthy();
    });

    it('should open the options popover when the user click on .sw-select__selection', async () => {
        const multiDataSelect = createMultiDataSelect();
        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const selectOptionsPopover = multiDataSelect.find(selector.multiDataSelect.popover);
        expect(selectOptionsPopover.isVisible()).toBeTruthy();
    });

    it('should show the select field\'s options popover', async () => {
        const messageAddData = 'global.sw-multi-tag-select.addData';
        const messageEnterValidData = 'global.sw-multi-tag-select.enterValidData';

        const multiDataSelect = createMultiDataSelect();
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

        const multiDataSelect = createMultiDataSelect({
            listeners: {
                change: changeSpy
            }
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

        const multiDataSelect = createMultiDataSelect({
            listeners: {
                change: changeSpy
            }
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

        const multiDataSelect = createMultiDataSelect();
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
                        amet: '95a5ab26-2512-4843-8288-871e593a81f1'
                    }
                }
            }
        };

        const multiDataSelect = createMultiDataSelect();

        expect(multiDataSelect.vm.getKey(subject, 'lorem', null)).toBe(subject.lorem);
        expect(multiDataSelect.vm.getKey(subject, 'ipsum.dolor.sit.amet', null)).toBe(subject.ipsum.dolor.sit.amet);
        expect(multiDataSelect.vm.getKey(subject, 'lorem.ipsum.dolor.sit.amet', 'Whoops!')).toBe('Whoops!');
    });

    it('should add a new value when the option popover is blurred', async () => {
        const changeSpy = jest.fn();
        const value = 'df8777d8-5969-475e-bbc2-f55a14d49ed7';

        const multiDataSelect = createMultiDataSelect({
            listeners: {
                change: changeSpy
            }
        });
        await multiDataSelect.find(selector.multiDataSelect.container).trigger('click');

        const input = multiDataSelect.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(multiDataSelect.vm.searchTerm).toBe(value);

        pressEspace(input);

        expect(multiDataSelect.vm.searchTerm).toBe('');
        expect(changeSpy).toHaveBeenCalledWith([value]);
    });
});
