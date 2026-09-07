import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(overrides = {}) {
    return mount(await wrapTestComponent('sw-order-modification-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
            },
        },
        props: {
            modification: {
                isNew: () => true,
                label: 'Loyalty discount',
                price: -10,
                taxRules: [],
                priceDefinition: null,
                type: null,
                ...overrides.modification,
            },
            order: {
                price: { totalPrice: 100 },
                lineItems: [],
                ...overrides.order,
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-modification-modal', () => {
    it('is invalid without a label', async () => {
        const wrapper = await createWrapper({ modification: { label: '' } });

        expect(wrapper.vm.isValid).toBe(false);
    });

    it('is invalid with a zero magnitude', async () => {
        const wrapper = await createWrapper({ modification: { price: 0 } });

        expect(wrapper.vm.isValid).toBe(false);
    });

    it('is valid with a label, a positive magnitude, and a direction', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.isValid).toBe(true);
    });

    it('locks the tax treatment for a modification the checkout pipeline contributed', async () => {
        const wrapper = await createWrapper({ modification: { type: 'promotion' } });

        expect(wrapper.vm.isLocked).toBe(true);
    });

    it('does not lock the tax treatment for a manually added modification', async () => {
        const wrapper = await createWrapper({ modification: { type: null } });

        expect(wrapper.vm.isLocked).toBe(false);
    });

    it('converts a reduction into a negative signed price on save', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.direction = 'reduction';
        wrapper.vm.magnitude = 25;

        wrapper.vm.onSave();

        expect(wrapper.vm.modification.price).toBe(-25);
        expect(wrapper.emitted('modal-save')).toBeTruthy();
        expect(wrapper.emitted('modal-save')[0][0]).toBe(wrapper.vm.modification);
    });

    it('converts a surcharge into a positive signed price on save', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.direction = 'surcharge';
        wrapper.vm.magnitude = 15;

        wrapper.vm.onSave();

        expect(wrapper.vm.modification.price).toBe(15);
    });

    it('does not save an invalid form', async () => {
        const wrapper = await createWrapper({ modification: { label: '' } });

        wrapper.vm.onSave();

        expect(wrapper.emitted('modal-save')).toBeFalsy();
    });

    it('nulls the tax rules when tax-exempt is enabled on save', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.taxExempt = true;

        wrapper.vm.onSave();

        expect(wrapper.vm.modification.taxRules).toBeNull();
    });
});
