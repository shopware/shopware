/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

const selector = {
    multiDataSelect: {
        container: '.sw-select__selection',
        input: '.sw-select-selection-list__input',
        popover: '.sw-select-result-list__content',
    },
};

const createWrapper = async (customOptions = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-multi-tag-select', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-label': true,
                'sw-button': true,
                'mt-floating-ui': true,
            },
        },
        props: {
            value: [],
            disabled: false,
        },
        ...customOptions,
    });

    await flushPromises();

    return wrapper;
};

const pressKey = async (el, key) => {
    await el.trigger('keydown', {
        key: key,
    });
};

const pressEnter = (el) => pressKey(el, 'Enter');
const pressEscape = (el) => pressKey(el, 'Escape');

describe('components/sw-multi-tag-select', () => {
    it('should open the options popover when the user click on .sw-select__selection', async () => {
        const wrapper = await createWrapper();

        await wrapper.find(selector.multiDataSelect.container).trigger('click');
        await flushPromises();

        const selectOptionsPopover = wrapper.find(selector.multiDataSelect.popover);
        expect(selectOptionsPopover.isVisible()).toBeTruthy();
    });

    it('should focus input when the user click on .sw-select__selection', async () => {
        const wrapper = await createWrapper();
        const focusSpy = jest.spyOn(wrapper.vm.$refs.selectionList, 'focus');

        wrapper.vm.setDropDown(true);
        expect(focusSpy).toHaveBeenCalled();
    });

    it("should show the select field's options popover", async () => {
        const wrapper = await createWrapper();

        await wrapper.find(selector.multiDataSelect.container).trigger('click');
        await flushPromises();

        const selectOptionsPopover = wrapper.find(selector.multiDataSelect.popover);
        expect(selectOptionsPopover.text()).toBe('global.sw-multi-tag-select.enterValidData');

        const input = wrapper.find(selector.multiDataSelect.input);
        await input.setValue('anything');

        expect(selectOptionsPopover.text()).toBe('global.sw-multi-tag-select.addData');
    });

    it('should add a new item when the user selects one using the enter key', async () => {
        const value = 'a16d4da0-4ba5-4c75-973b-515e23e6498a';

        const wrapper = await createWrapper();

        const input = wrapper.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(wrapper.vm.searchTerm).toBe(value);

        await pressEnter(input);
        await flushPromises();

        expect(wrapper.emitted('update:value')).toStrictEqual([[[value]]]);
        expect(wrapper.vm.searchTerm).toBe('');
    });

    it('should add a new item when the user selects one using the popover', async () => {
        const value = '5f8c8049-ee9f-4f10-b8b6-5daa9536e0c4';

        const wrapper = await createWrapper();

        await wrapper.find(selector.multiDataSelect.container).trigger('click');
        await flushPromises();

        const input = wrapper.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(wrapper.vm.searchTerm).toBe(value);

        const addItemPopover = wrapper.find('.sw-multi-tag-select-valid');
        await addItemPopover.trigger('click');

        expect(wrapper.emitted('update:value')).toStrictEqual([[[value]]]);
        expect(wrapper.vm.searchTerm).toBe('');
    });

    it("should set inputIsValid to false, when there's no searchTerm given", async () => {
        const value = 'a676344c-c0dd-49e5-8fbb-5f570c27762c';

        const wrapper = await createWrapper();
        const input = wrapper.find(selector.multiDataSelect.input);

        expect(wrapper.vm.inputIsValid).toBeFalsy();
        expect(wrapper.vm.errorObject).toBeNull();

        await input.setValue(value);

        expect(wrapper.vm.searchTerm).toBe(value);
        expect(wrapper.vm.inputIsValid).toBeTruthy();

        await input.setValue('');

        expect(wrapper.vm.inputIsValid).toBeFalsy();
        expect(wrapper.vm.errorObject).toBeNull();
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

        const wrapper = await createWrapper();

        expect(wrapper.vm.getKey(subject, 'lorem', null)).toBe(subject.lorem);
        expect(wrapper.vm.getKey(subject, 'ipsum.dolor.sit.amet', null)).toBe(subject.ipsum.dolor.sit.amet);
        expect(wrapper.vm.getKey(subject, 'lorem.ipsum.dolor.sit.amet', 'Whoops!')).toBe('Whoops!');
    });

    it('should add a new value when the option popover is blurred', async () => {
        const value = 'df8777d8-5969-475e-bbc2-f55a14d49ed7';

        const wrapper = await createWrapper();
        await wrapper.find(selector.multiDataSelect.container).trigger('click');
        await flushPromises();

        const input = wrapper.find(selector.multiDataSelect.input);
        await input.setValue(value);

        expect(wrapper.vm.searchTerm).toBe(value);

        await pressEscape(input);

        expect(wrapper.vm.searchTerm).toBe('');
        expect(wrapper.emitted('update:value')).toStrictEqual([[[value]]]);
    });

    it('should be disabled correctly', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('.sw-select input').wrapperElement).toBeDisabled();

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('.sw-select input').wrapperElement).toBeEnabled();
    });

    it('should show only five tags of selection list and convert to a object', async () => {
        const multiDataSelect = await createWrapper();

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
        const multiDataSelect = await createWrapper();

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
        const multiDataSelect = await createWrapper();

        await multiDataSelect.setProps({
            value: [],
        });

        multiDataSelect.vm.removeLastItem();

        expect(multiDataSelect.emitted()['update:value']).toBeFalsy();
    });

    it('should expand value first when use keyboard delete last item', async () => {
        const multiDataSelect = await createWrapper();

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
        expect(multiDataSelect.emitted()['update:value']).toBeFalsy();
    });

    it('should emmited a update value when remove item', async () => {
        const multiDataSelect = await createWrapper();

        await multiDataSelect.setProps({
            value: [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
                'Selection5',
            ],
        });

        multiDataSelect.vm.$emit('update:value', [
            'Selection1',
            'Selection2',
            'Selection3',
            'Selection4',
        ]);
        multiDataSelect.vm.remove({ value: 'Selection5' });

        expect(multiDataSelect.emitted()['update:value']).toBeTruthy();
        expect(multiDataSelect.emitted()['update:value'][0]).toEqual([
            [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
            ],
        ]);
    });

    it('should remove the last item', async () => {
        const multiDataSelect = await createWrapper();

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

        expect(multiDataSelect.emitted()['update:value']).toBeTruthy();
        expect(multiDataSelect.emitted()['update:value'][0]).toEqual([
            [
                'Selection1',
                'Selection2',
                'Selection3',
                'Selection4',
            ],
        ]);
    });

    it('should expand value limit', async () => {
        const multiDataSelect = await createWrapper();

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
