/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-confirm-field', { sync: true }), {
        props: {
            value: 'Initial value',
            ...props,
        },
        global: {
            stubs: {
                'mt-text-field': {
                    props: [
                        'modelValue',
                        'disabled',
                        'error',
                        'required',
                    ],
                    emits: [
                        'focus',
                        'blur',
                        'keyup.enter',
                        'keyup.esc',
                        'update:model-value',
                    ],
                    template: `
                        <input
                            class="mt-text-field"
                            :value="modelValue"
                            @focus="$emit('focus', $event)"
                            @blur="$emit('blur', $event)"
                            @input="$emit('update:model-value', $event.target.value)"
                        >
                    `,
                },
                'mt-button': {
                    template: '<button class="mt-button" type="button"><slot></slot></button>',
                },
                'mt-icon': true,
            },
        },
    });
}

describe('src/app/component/form/sw-confirm-field', () => {
    it('should only show confirm actions after the value changed', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.mt-text-field');

        await input.trigger('focus');

        expect(wrapper.vm.showActionButtons).toBe(false);
        expect(wrapper.classes()).not.toContain('sw-confirm-field--editing');

        await input.setValue('Updated value');

        expect(wrapper.vm.showActionButtons).toBe(true);
        expect(wrapper.classes()).toContain('sw-confirm-field--editing');
    });
});
