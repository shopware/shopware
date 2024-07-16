/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-bulk-edit-change-type', { sync: true }), {
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-icon': true,
                'sw-field-error': await wrapTestComponent('sw-field-error'),
            },
        },
        props: {
            value: 'overwrite',
            allowOverwrite: true,
            allowClear: true,
            ...propsData,
        },
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-change-type', () => {
    it('should be change to clear and hide the input field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isDisplayingValue).toBeTruthy();

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectClear = wrapper.find('.sw-select-option--1');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.clear');
        await selectClear.trigger('click');
        await flushPromises();

        expect(wrapper.vm.isDisplayingValue).toBeFalsy();
        expect(wrapper.emitted('update:value')[0]).toEqual(['clear']);
    });

    it('should be change from clear to add and show the input field', async () => {
        const wrapper = await createWrapper({
            value: 'overwrite',
            allowOverwrite: true,
            allowClear: true,
            allowAdd: true,
        });
        await flushPromises();

        expect(wrapper.vm.isDisplayingValue).toBeTruthy();

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectClear = wrapper.find('.sw-select-option--1');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.clear');
        await selectClear.trigger('click');
        await flushPromises();

        expect(wrapper.vm.isDisplayingValue).toBeFalsy();

        await selection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectAdd = wrapper.find('.sw-select-option--2');
        expect(selectAdd.text()).toBe('sw-bulk-edit.changeTypes.add');
        await selectAdd.trigger('click');

        expect(wrapper.vm.isDisplayingValue).toBeTruthy();
    });

    it('should be display the allow options', async () => {
        const wrapper = await createWrapper({
            value: 'overwrite',
            allowOverwrite: false,
            allowClear: false,
            allowAdd: true,
            allowRemove: true,
        });
        await flushPromises();

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectClear = wrapper.find('.sw-select-option--0');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.add');

        const selectRemove = wrapper.find('.sw-select-option--1');
        expect(selectRemove.text()).toBe('sw-bulk-edit.changeTypes.remove');
    });
});
