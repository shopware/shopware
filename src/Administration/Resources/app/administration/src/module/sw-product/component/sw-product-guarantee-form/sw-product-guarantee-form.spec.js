/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-product/component/sw-product-guarantee-form', () => {
    let wrapper;
    let store;

    async function createWrapper(propsOverride = {}, privileges = [], productOverride = {}) {
        store = Shopware.Store.get('swProductDetail');
        store.product.id = 'productId';
        store.product.getEntityName = () => 'product';
        store.product.guaranteeMonths = 36;
        store.product.guaranteeConfirmed = false;
        store.product.manufacturerNumber = 'MPN-1';
        store.product.manufacturer = { name: 'Manufacturer', translated: { name: 'Manufacturer' } };
        store.parentProduct = {};

        Object.assign(store.product, productOverride);

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
                                <span
                                    v-if="error"
                                    class="mt-number-field__error"
                                >{{ error.code }}</span>
                            </div>`,
                        props: [
                            'modelValue',
                            'label',
                            'disabled',
                            'min',
                            'max',
                            'step',
                            'error',
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
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot></slot></div>',
                        props: [
                            'variant',
                            'closable',
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
        Shopware.Store.get('error').api = {};
        wrapper = await createWrapper({}, ['product.editor']);
    });

    it('should render the guarantee months and confirmation fields with current values', async () => {
        expect(wrapper.vm.product.guaranteeMonths).toBe(36);
        expect(wrapper.vm.product.guaranteeConfirmed).toBe(false);

        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        expect(monthsField.element.value).toBe('36');
        expect(confirmedField.element.checked).toBe(false);
    });

    it('should be able to change the guarantee months and confirmation', async () => {
        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        await monthsField.setValue(42);
        await confirmedField.setValue(true);

        expect(store.product.guaranteeMonths).toBe(42);
        expect(store.product.guaranteeConfirmed).toBe(true);
    });

    it('should only offer valid guarantee durations in the stepper', async () => {
        const monthsField = wrapper.findComponent('.mt-number-field');

        expect(monthsField.props('min')).toBe(30);
        expect(monthsField.props('step')).toBe(6);
        expect(monthsField.props('max')).toBeUndefined();
    });

    it.each([
        25,
        31,
        6,
    ])('should validate a typed guarantee duration of %s before saving', async (guaranteeMonths) => {
        await wrapper.find('.mt-number-field input').setValue(guaranteeMonths);

        expect(store.product.guaranteeMonths).toBe(guaranteeMonths);
        expect(wrapper.find('.mt-number-field__error').text()).toBe('INVALID_GARAN_GUARANTEE_MONTHS');
    });

    it.each([
        30,
        36,
        42,
    ])('should accept a guarantee duration of %s without an error', async (guaranteeMonths) => {
        store.product.guaranteeMonths = guaranteeMonths;
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').exists()).toBe(false);
    });

    it('should accept an empty guarantee duration without an error', async () => {
        store.product.guaranteeMonths = null;
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').exists()).toBe(false);
    });

    it('should not validate a guarantee duration inherited from the parent product', async () => {
        store.product.guaranteeMonths = null;
        store.parentProduct = { id: 'parentId', guaranteeMonths: 12 };
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').exists()).toBe(false);
    });

    it.each([
        25,
        31,
    ])('should show the validation error for %s guarantee months', async (guaranteeMonths) => {
        store.product.guaranteeMonths = guaranteeMonths;

        Shopware.Store.get('error').addApiError({
            expression: 'product.productId.guaranteeMonths',
            error: {
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
            },
        });
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').text()).toBe('INVALID_GARAN_GUARANTEE_MONTHS');
    });

    it('should drop the duration error of the last save once the value is valid again', async () => {
        store.product.guaranteeMonths = 25;

        Shopware.Store.get('error').addApiError({
            expression: 'product.productId.guaranteeMonths',
            error: {
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
            },
        });
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').exists()).toBe(true);

        await wrapper.find('.mt-number-field input').setValue(30);

        expect(wrapper.find('.mt-number-field__error').exists()).toBe(false);
    });

    it('should keep an api error that is not about the duration rule', async () => {
        Shopware.Store.get('error').addApiError({
            expression: 'product.productId.guaranteeMonths',
            error: {
                code: 'FRAMEWORK__MISSING_PRIVILEGE_ERROR',
                detail: 'Missing permissions',
            },
        });
        await flushPromises();

        expect(wrapper.find('.mt-number-field__error').text()).toBe('FRAMEWORK__MISSING_PRIVILEGE_ERROR');
    });

    it('should disable the fields when allowEdit is false', async () => {
        wrapper = await createWrapper({ allowEdit: false }, ['product.editor']);

        const monthsField = wrapper.find('.mt-number-field input');
        const confirmedField = wrapper.find('.mt-switch input');

        expect(monthsField.element.disabled).toBe(true);
        expect(confirmedField.element.disabled).toBe(true);
    });

    describe('unmet label requirements notice', () => {
        it('should not be shown while the label is deactivated', async () => {
            store.product.guaranteeConfirmed = false;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            await flushPromises();

            expect(wrapper.find('.mt-banner').exists()).toBe(false);
        });

        it('should not be shown while all requirements are met', async () => {
            store.product.guaranteeConfirmed = true;
            await flushPromises();

            expect(wrapper.find('.mt-banner').exists()).toBe(false);
        });

        it.each([
            [
                'guarantee duration',
                { guaranteeMonths: 12 },
                'sw-product.settingsForm.noticeGuaranteeRequirementMonths',
            ],
            [
                'manufacturer',
                { manufacturer: null },
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturer',
            ],
            [
                'manufacturer number',
                { manufacturerNumber: '  ' },
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber',
            ],
        ])('should name the missing %s', async (_name, productOverride, snippet) => {
            store.product.guaranteeConfirmed = true;
            Object.assign(store.product, productOverride);
            await flushPromises();

            const requirements = wrapper.findAll('.mt-banner li');

            expect(requirements).toHaveLength(1);
            expect(requirements.at(0).text()).toBe(snippet);
        });

        it('should name every missing requirement', async () => {
            store.product.guaranteeConfirmed = true;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            await flushPromises();

            expect(wrapper.findAll('.mt-banner li').map((item) => item.text())).toEqual([
                'sw-product.settingsForm.noticeGuaranteeRequirementMonths',
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturer',
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber',
            ]);
        });

        it('should not be closable', async () => {
            store.product.guaranteeConfirmed = true;
            store.product.manufacturerNumber = null;
            await flushPromises();

            expect(wrapper.findComponent('.mt-banner').props('closable')).toBeUndefined();
        });

        it('should resolve the requirements inherited from the parent product', async () => {
            store.product.guaranteeConfirmed = null;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            store.parentProduct = {
                id: 'parentId',
                guaranteeConfirmed: true,
                guaranteeMonths: 36,
                manufacturer: { translated: { name: 'Parent manufacturer' } },
                manufacturerNumber: 'MPN-PARENT',
            };
            await flushPromises();

            expect(wrapper.find('.mt-banner').exists()).toBe(false);
        });

        it('should name the requirement a variant does not inherit either', async () => {
            store.product.guaranteeConfirmed = true;
            store.product.manufacturerNumber = null;
            store.parentProduct = {
                id: 'parentId',
                guaranteeMonths: 36,
                manufacturer: { translated: { name: 'Parent manufacturer' } },
                manufacturerNumber: null,
            };
            await flushPromises();

            const requirements = wrapper.findAll('.mt-banner li');

            expect(requirements).toHaveLength(1);
            expect(requirements.at(0).text()).toBe('sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber');
        });
    });
});
