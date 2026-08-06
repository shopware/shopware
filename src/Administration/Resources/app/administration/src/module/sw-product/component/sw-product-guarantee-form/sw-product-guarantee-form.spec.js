/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-product/component/sw-product-guarantee-form', () => {
    let wrapper;
    let store;

    async function createWrapper(propsOverride = {}, privileges = []) {
        store = Shopware.Store.get('swProductDetail');
        store.product.guaranteeMonths = 12;
        store.product.guaranteeConfirmed = false;

        const acl = {
            can: (privilege) => {
                if (!privilege) {
                    return true;
                }

                return privileges.includes(privilege);
            },
        };

        return mount(await wrapTestComponent('sw-product-guarantee-form', { sync: true }), {
            props: {
                allowEdit: true,
                ...propsOverride,
            },
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': {
                        template: `
                            <div class="sw-inherit-wrapper">
                                <slot name="content" v-bind="{
                                    currentValue: value,
                                    isInherited: false,
                                    updateCurrentValue: (val) => $emit('update:value', val)
                                }"></slot>
                            </div>`,
                        props: [
                            'value',
                            'hasParent',
                            'inheritedValue',
                        ],
                    },
                    'mt-number-field': {
                        template: `
                            <div class="mt-number-field">
                                <label>{{ label }}</label>
                                <input
                                    type="number"
                                    :value="modelValue"
                                    :disabled="disabled"
                                    @input="$emit('update:model-value', Number($event.target.value))"
                                />
                            </div>`,
                        props: [
                            'modelValue',
                            'label',
                            'disabled',
                        ],
                    },
                    'mt-switch': {
                        template: `
                            <div class="mt-switch">
                                <label>{{ label }}</label>
                                <input
                                    type="checkbox"
                                    :checked="modelValue"
                                    :disabled="disabled"
                                    @change="$emit('update:model-value', $event.target.checked)"
                                />
                            </div>`,
                        props: [
                            'modelValue',
                            'label',
                            'disabled',
                        ],
                    },
                },
                provide: {
                    acl,
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper({}, ['product.editor']);
    });

    it('should render the guarantee months and confirmation fields with current values', async () => {
        expect(wrapper.vm.product.guaranteeMonths).toBe(12);
        expect(wrapper.vm.product.guaranteeConfirmed).toBe(false);

        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        expect(monthsField.element.value).toBe('12');
        expect(confirmedField.element.checked).toBe(false);
    });

    it('should be able to change the guarantee months and confirmation', async () => {
        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        await monthsField.setValue(24);
        await confirmedField.setValue(true);

        expect(store.product.guaranteeMonths).toBe(24);
        expect(store.product.guaranteeConfirmed).toBe(true);
    });

    it('should disable the fields when allowEdit is false', async () => {
        wrapper = await createWrapper({ allowEdit: false }, ['product.editor']);

        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        expect(monthsField.element.disabled).toBe(true);
        expect(confirmedField.element.disabled).toBe(true);
    });
});
