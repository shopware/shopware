import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

/**
 * Builds a minimal stand-in for a DAL EntityCollection exposing the `has`/`get`
 * lookups the component relies on.
 */
function createAddressCollection(addresses = []) {
    return {
        has: (id) => addresses.some((address) => address.id === id),
        get: (id) => addresses.find((address) => address.id === id) ?? null,
    };
}

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-customer-address-form-options', { sync: true }), {
        global: {
            stubs: {
                'mt-checkbox': {
                    props: [
                        'checked',
                        'disabled',
                    ],
                    template: '<input type="checkbox" class="mt-checkbox" :checked="checked" :disabled="disabled">',
                },
                'sw-custom-field-set-renderer': {
                    name: 'sw-custom-field-set-renderer',
                    props: ['disabled'],
                    template: '<div class="sw-custom-field-set-renderer"></div>',
                },
            },
        },
        props: {
            customer: {
                defaultShippingAddressId: 'shipping-address-id',
                defaultBillingAddressId: 'billing-address-id',
            },
            address: {
                id: 'address-id',
            },
            customFieldSets: [],
            ...props,
        },
    });
}

describe('module/sw-customer/component/sw-customer-address-form-options', () => {
    it('should disable address options and custom fields', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        wrapper.findAllComponents('.mt-checkbox').forEach((checkbox) => {
            expect(checkbox.props('disabled')).toBe(true);
        });
        expect(wrapper.getComponent('.sw-custom-field-set-renderer').props('disabled')).toBe(true);
    });

    it('should check the boxes when the edited customer address is the default via id', async () => {
        const wrapper = await createWrapper({
            address: { id: 'shipping-address-id' },
        });

        expect(wrapper.vm.isDefaultShippingAddressId).toBe(true);
        expect(wrapper.vm.isDefaultBillingAddressId).toBe(false);
    });

    it('should leave the boxes unchecked when the address is neither the default nor a matching snapshot', async () => {
        const wrapper = await createWrapper({
            address: { id: 'unrelated-address-id', hash: 'unrelated-hash' },
        });

        expect(wrapper.vm.isDefaultShippingAddressId).toBe(false);
        expect(wrapper.vm.isDefaultBillingAddressId).toBe(false);
    });

    it('should check the boxes for an order address snapshot matching the default by content hash', async () => {
        const wrapper = await createWrapper({
            customer: {
                defaultShippingAddressId: 'shipping-address-id',
                defaultBillingAddressId: 'billing-address-id',
                addresses: createAddressCollection([
                    { id: 'shipping-address-id', hash: 'shipping-hash' },
                    { id: 'billing-address-id', hash: 'billing-hash' },
                ]),
            },
            // Order addresses are snapshot copies with their own id but the same content hash.
            address: { id: 'order-address-id', hash: 'shipping-hash' },
        });

        expect(wrapper.vm.isDefaultShippingAddressId).toBe(true);
        expect(wrapper.vm.isDefaultBillingAddressId).toBe(false);
    });

    it('should not check the boxes for an order address snapshot whose hash differs from the default', async () => {
        const wrapper = await createWrapper({
            customer: {
                defaultShippingAddressId: 'shipping-address-id',
                defaultBillingAddressId: 'billing-address-id',
                addresses: createAddressCollection([
                    { id: 'shipping-address-id', hash: 'shipping-hash' },
                    { id: 'billing-address-id', hash: 'billing-hash' },
                ]),
            },
            address: { id: 'order-address-id', hash: 'outdated-hash' },
        });

        expect(wrapper.vm.isDefaultShippingAddressId).toBe(false);
        expect(wrapper.vm.isDefaultBillingAddressId).toBe(false);
    });

    it('should not hash-match a stored customer address that only shares its content with the default', async () => {
        const wrapper = await createWrapper({
            customer: {
                defaultShippingAddressId: 'shipping-address-id',
                defaultBillingAddressId: 'billing-address-id',
                addresses: createAddressCollection([
                    { id: 'shipping-address-id', hash: 'shared-hash' },
                    { id: 'billing-address-id', hash: 'billing-hash' },
                    // A distinct stored address with identical content to the default.
                    { id: 'duplicate-address-id', hash: 'shared-hash' },
                ]),
            },
            address: { id: 'duplicate-address-id', hash: 'shared-hash' },
        });

        expect(wrapper.vm.isDefaultShippingAddressId).toBe(false);
        expect(wrapper.vm.isDefaultBillingAddressId).toBe(false);
    });
});
