import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-popover';

function createWrapper(propsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    return shallowMount(Shopware.Component.build('sw-bulk-edit-change-type'), {
        localVue,
        stubs: {
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-icon': true,
            'sw-field-error': Shopware.Component.build('sw-field-error')
        },
        propsData: {
            value: 'overwrite',
            allowOverwrite: true,
            allowClear: true,
            ...propsData
        }
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-change-type', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be change to clear and hide the input field', async () => {
        expect(wrapper.vm.isDisplayingValue).toBeTruthy();

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const selectClear = wrapper.find('.sw-select-option--1');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.clear');
        await selectClear.trigger('click');

        expect(wrapper.vm.isDisplayingValue).toBeFalsy();
        expect(wrapper.emitted('change')[0]).toEqual(['clear']);
    });

    it('should be change from clear to add and show the input field', async () => {
        wrapper = createWrapper({
            value: 'overwrite',
            allowOverwrite: true,
            allowClear: true,
            allowAdd: true
        });

        expect(wrapper.vm.isDisplayingValue).toBeTruthy();

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const selectClear = wrapper.find('.sw-select-option--1');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.clear');
        await selectClear.trigger('click');

        expect(wrapper.vm.isDisplayingValue).toBeFalsy();

        await wrapper.vm.$nextTick();

        await selection.find('.sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const selectAdd = wrapper.find('.sw-select-option--2');
        expect(selectAdd.text()).toBe('sw-bulk-edit.changeTypes.add');
        await selectAdd.trigger('click');

        expect(wrapper.vm.isDisplayingValue).toBeTruthy();
    });

    it('should be display the allow options', async () => {
        wrapper = createWrapper({
            value: 'overwrite',
            allowOverwrite: false,
            allowClear: false,
            allowAdd: true,
            allowRemove: true
        });

        const selection = wrapper.find('.sw-bulk-edit-change-type__selection');
        await selection.find('.sw-select__selection').trigger('click');

        const selectClear = wrapper.find('.sw-select-option--0');
        expect(selectClear.text()).toBe('sw-bulk-edit.changeTypes.add');

        const selectRemove = wrapper.find('.sw-select-option--1');
        expect(selectRemove.text()).toBe('sw-bulk-edit.changeTypes.remove');
    });
});
