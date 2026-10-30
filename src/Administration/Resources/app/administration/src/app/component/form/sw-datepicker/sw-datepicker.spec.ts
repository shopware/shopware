/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

function createFieldStub(name: string, modelProp: 'modelValue' | 'value') {
    return {
        props: [modelProp],
        emits: [`update:${modelProp}`],
        template: `<input class="field-stub ${name}" @input="$emit('update:${modelProp}', $event.target.value)" />`,
    };
}

async function createWrapper(options: { props?: Record<string, unknown>; attrs?: Record<string, unknown> } = {}) {
    return mount(await wrapTestComponent('sw-datepicker', { sync: true }), {
        global: {
            stubs: {
                'mt-datepicker': createFieldStub('mt-datepicker', 'modelValue'),
                'sw-datepicker-deprecated': createFieldStub('sw-datepicker-deprecated', 'value'),
            },
        },
        ...options,
    });
}

describe('src/app/component/base/sw-datepicker', () => {
    it('should render the mt-datepicker', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.mt-datepicker').exists()).toBe(true);
        expect(wrapper.find('.sw-datepicker-deprecated').exists()).toBe(false);
    });

    it.each([
        ['mt-datepicker', false, 'onUpdate:modelValue'],
        ['deprecated datepicker', true, 'onUpdate:value'],
    ])('should notify the parent once per change from the %s', async (_, deprecated, listenerName) => {
        const listener = jest.fn();
        const wrapper = await createWrapper({
            props: { deprecated },
            attrs: { [listenerName]: listener },
        });

        await wrapper.find('.field-stub').setValue('2026-10-16');

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener).toHaveBeenCalledWith('2026-10-16');
    });
});
