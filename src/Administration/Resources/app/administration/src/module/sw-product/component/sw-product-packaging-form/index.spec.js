/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const inheritedMeteorFields = [
    {
        selector: '.sw-product-packaging-form__purchase-unit-field',
        property: 'purchaseUnit',
        inheritedValue: 5,
        overrideValue: 7,
    },
    {
        selector: '.sw-product-packaging-form__reference-unit-field',
        property: 'referenceUnit',
        inheritedValue: 1,
        overrideValue: 2,
    },
    {
        selector: '.sw-product-packaging-form__pack-unit-field',
        property: 'packUnit',
        inheritedValue: 'box',
        overrideValue: 'bundle',
    },
    {
        selector: '.sw-product-packaging-form__pack-unit-plural-field',
        property: 'packUnitPlural',
        inheritedValue: 'boxes',
        overrideValue: 'bundles',
    },
];

const inheritedFieldValues = {
    purchaseUnit: 5,
    unitId: 'parent-unit-id',
    referenceUnit: 1,
    packUnit: 'box',
    packUnitPlural: 'boxes',
};

async function createWrapper({ allowEdit = true, showSettingPackaging = true, product = {}, parentProduct = {} } = {}) {
    const store = Shopware.Store.get('swProductDetail');

    store.$reset();
    store.product = {
        purchaseUnit: null,
        unitId: null,
        referenceUnit: null,
        packUnit: null,
        packUnitPlural: null,
        ...product,
    };
    store.parentProduct = {
        id: 'parent-product-id',
        ...inheritedFieldValues,
        ...parentProduct,
    };

    const fieldStub = {
        props: [
            'isInheritanceField',
            'isInherited',
            'modelValue',
            'disabled',
        ],
        emits: [
            'update:model-value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        template: '<input :value="modelValue" :disabled="disabled" />',
    };

    return mount(await wrapTestComponent('sw-product-packaging-form', { sync: true }), {
        props: {
            allowEdit,
            showSettingPackaging,
        },
        global: {
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-inheritance-switch': {
                    props: [
                        'isInherited',
                        'disabled',
                    ],
                    emits: [
                        'inheritance-restore',
                        'inheritance-remove',
                    ],
                    template: '<button class="sw-inheritance-switch" type="button"></button>',
                },
                'sw-entity-single-select': {
                    props: [
                        'value',
                        'disabled',
                    ],
                    emits: [
                        'update:value',
                    ],
                    template: '<select :value="value" :disabled="disabled"></select>',
                },
                'mt-number-field': fieldStub,
                'mt-text-field': fieldStub,
                'sw-help-text': true,
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-packaging-form', () => {
    it('renders inherited parent values for all packaging fields', async () => {
        const wrapper = await createWrapper();

        inheritedMeteorFields.forEach(({ selector, inheritedValue }) => {
            const field = wrapper.getComponent(selector);

            expect(field.props('modelValue')).toBe(inheritedValue);
            expect(field.props('isInheritanceField')).toBe(true);
            expect(field.props('isInherited')).toBe(true);
            expect(field.props('disabled')).toBe(true);
        });

        const unitSelect = wrapper.getComponent('.sw-select-product__select_unit');

        expect(unitSelect.props('value')).toBe(inheritedFieldValues.unitId);
        expect(unitSelect.props('disabled')).toBe(true);
    });

    it.each(inheritedMeteorFields)(
        'wires inheritance controls for $property',
        async ({ selector, property, inheritedValue, overrideValue }) => {
            const wrapper = await createWrapper();
            const store = Shopware.Store.get('swProductDetail');
            const field = wrapper.getComponent(selector);

            await field.vm.$emit('inheritance-remove');
            await nextTick();

            expect(store.product[property]).toBe(inheritedValue);
            expect(field.props('isInherited')).toBe(false);
            expect(field.props('disabled')).toBe(false);

            await field.vm.$emit('update:model-value', overrideValue);
            await nextTick();

            expect(store.product[property]).toBe(overrideValue);

            await field.vm.$emit('inheritance-restore');
            await nextTick();

            expect(store.product[property]).toBeNull();
            expect(field.props('isInherited')).toBe(true);
            expect(field.props('disabled')).toBe(true);
        },
    );

    it('wires inheritance controls for the product unit select', async () => {
        const wrapper = await createWrapper();
        const store = Shopware.Store.get('swProductDetail');
        const getUnitSelect = () => wrapper.getComponent('.sw-select-product__select_unit');
        const inheritanceSwitch = wrapper.getComponent('.sw-inheritance-switch');

        expect(inheritanceSwitch.props('isInherited')).toBe(true);

        await inheritanceSwitch.vm.$emit('inheritance-remove');
        await nextTick();

        expect(store.product.unitId).toBe(inheritedFieldValues.unitId);
        expect(getUnitSelect().props('disabled')).toBe(false);

        await getUnitSelect().vm.$emit('update:value', 'variant-unit-id');
        await nextTick();

        expect(store.product.unitId).toBe('variant-unit-id');

        await inheritanceSwitch.vm.$emit('inheritance-restore');
        await nextTick();

        expect(store.product.unitId).toBeNull();
        expect(getUnitSelect().props('disabled')).toBe(true);
        expect(getUnitSelect().props('value')).toBe(inheritedFieldValues.unitId);
    });

    it('disables all fields when editing is not allowed', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            product: {
                purchaseUnit: 5,
                unitId: 'variant-unit-id',
                referenceUnit: 1,
                packUnit: 'box',
                packUnitPlural: 'boxes',
            },
        });

        inheritedMeteorFields.forEach(({ selector }) => {
            expect(wrapper.getComponent(selector).props('disabled')).toBe(true);
        });

        expect(wrapper.getComponent('.sw-select-product__select_unit').props('disabled')).toBe(true);
    });
});
