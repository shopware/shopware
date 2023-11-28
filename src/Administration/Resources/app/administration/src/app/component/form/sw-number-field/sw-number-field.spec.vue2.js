/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

const createWrapper = async (additionalOptions = {}, value = null) => {
    return shallowMount(await Shopware.Component.build('sw-number-field'), {
        stubs: {
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': {
                template: '<div></div>',
            },
        },
        provide: {
            validationService: {},
        },
        propsData: {
            value,
        },
        ...additionalOptions,
    });
};

describe('app/component/form/sw-number-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should set value 0 when user deletes everything', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        // expect 5
        expect(wrapper.emitted('change')[0]).toEqual([5]);
        expect(input.element.value).toBe('5');

        // clear input
        await input.setValue('');
        await input.trigger('change');

        // expect 0
        expect(wrapper.emitted('change')[1]).toEqual([0]);
        expect(input.element.value).toBe('0');
    });

    it('should set value 2 when user deletes everything via input change and min is set to 2', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ min: 2 });

        const input = wrapper.find('input');

        // type "10"
        await input.setValue('10');
        await input.trigger('change');

        // expect 10
        expect(wrapper.emitted('change')[0]).toEqual([10]);
        expect(input.element.value).toBe('10');

        // clear input
        await input.setValue('');

        const inputChangeEvt = wrapper.emitted('input-change');
        expect(inputChangeEvt[inputChangeEvt.length - 1]).toEqual([2]);
    });

    it('should set value 0 when user deletes everything via input change and min is not set', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('input');

        // type "10"
        await input.setValue('10');
        await input.trigger('change');

        // expect 5
        expect(wrapper.emitted('change')[0]).toEqual([10]);
        expect(input.element.value).toBe('10');

        // clear input
        await input.setValue('');

        const inputChangeEvt = wrapper.emitted('input-change');
        expect(inputChangeEvt[inputChangeEvt.length - 1]).toEqual([0]);
    });

    it('should emit input change event with NaN when allowEmpty is true and user deletes everything', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ allowEmpty: true });

        const input = wrapper.find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        // expect 5
        expect(wrapper.emitted('change')[0]).toEqual([5]);
        expect(input.element.value).toBe('5');

        // clear input
        await input.setValue('');

        const inputChangeEvt = wrapper.emitted('input-change');
        expect(inputChangeEvt[inputChangeEvt.length - 1]).toEqual([NaN]);
    });

    it('should fill digits when appropriate', async () => {
        const wrapper = await createWrapper({ propsData: { fillDigits: true } });

        const input = wrapper.find('input');

        await input.setValue('5');
        await input.trigger('change');
        expect(input.element.value).toBe('5.00');

        await wrapper.setProps({ digits: 1 });
        await input.setValue('5.1');
        await input.trigger('change');
        expect(input.element.value).toBe('5.1');

        await wrapper.setProps({ value: 5.0 });
        await input.trigger('change');
        await input.trigger('change');
        expect(input.element.value).toBe('5.0');

        await input.setValue('5.0001');
        await input.trigger('change');
        expect(input.element.value).toBe('5.0001');
    });

    it('should not fill digits when not appropriate', async () => {
        const wrapper = await createWrapper({
            propsData: {
                fillDigits: true,
                numberType: 'int',
            },
        });

        const input = wrapper.find('input');
        await input.setValue('5');
        await input.trigger('change');
        expect(input.element.value).toBe('5');
    });

    it('should not fill digits when disabled (default)', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('input');
        await input.setValue('5');
        await input.trigger('change');
        expect(input.element.value).toBe('5');
    });

    it('should clear input field when user deletes everything and emits null', async () => {
        const wrapper = await createWrapper();

        // set property allowEmpty to true
        await wrapper.setProps({
            allowEmpty: true,
        });

        const input = wrapper.find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        // expect 5
        expect(wrapper.emitted('change')[0]).toEqual([5]);
        expect(input.element.value).toBe('5');

        // clear input
        await input.setValue('');
        await input.trigger('change');

        // expect null and empty input
        expect(wrapper.emitted('change')[1]).toEqual([null]);
        expect(input.element.value).toBe('');
    });

    it('should show the label from the property', async () => {
        const wrapper = await createWrapper({
            propsData: {
                label: 'Label from prop',
                value: null,
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            propsData: {
                label: 'Label from prop',
                value: null,
            },
            scopedSlots: {
                label: '<template>Label from slot</template>',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('should work with positive numbers', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('input');

        await input.setValue('1');
        await input.trigger('change');
        expect(input.element.value).toBe('1');

        await input.setValue('12345');
        await input.trigger('change');
        expect(input.element.value).toBe('12345');

        await input.setValue('12345.6');
        await input.trigger('change');
        expect(input.element.value).toBe('12345.6');

        await input.setValue('12345.12345');
        await input.trigger('change');
        expect(input.element.value).toBe('12345.12');
    });

    it('should work with negative numbers', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('input');

        await input.setValue('-1');
        await input.trigger('change');
        expect(input.element.value).toBe('-1');

        await input.setValue('-12345');
        await input.trigger('change');
        expect(input.element.value).toBe('-12345');

        await input.setValue('-0.5');
        await input.trigger('change');
        expect(input.element.value).toBe('-0.5');

        await input.setValue('-12345.12345');
        await input.trigger('change');
        expect(input.element.value).toBe('-12345.12');
    });

    it('should work with point and comma as decimal separator', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('input');

        await input.setValue('11.22');
        await input.trigger('change');
        expect(input.element.value).toBe('11.22');

        await input.setValue('22,33');
        await input.trigger('change');
        expect(input.element.value).toBe('22.33');
    });

    it('should round decimal places', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('input');

        await input.setValue('1.234');
        await input.trigger('change');
        expect(input.element.value).toBe('1.23');

        await input.setValue('1.235');
        await input.trigger('change');
        expect(input.element.value).toBe('1.24');
    });

    it('should remove scientific notation and convert to human readable', async () => {
        const wrapper = await createWrapper({}, 0.0000001);
        const input = wrapper.find('input');

        expect(input.exists()).toBe(true);
        expect(input.element.value).toBe('0');
    });
});
