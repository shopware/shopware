import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-customer-address-form-options', { sync: true }), {
        global: {
            stubs: {
                'mt-checkbox': {
                    props: ['disabled'],
                    template: '<input class="mt-checkbox" :disabled="disabled">',
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
});
