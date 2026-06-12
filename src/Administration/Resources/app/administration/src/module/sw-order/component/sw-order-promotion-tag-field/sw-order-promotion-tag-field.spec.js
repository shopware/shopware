import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-promotion-tag-field', { sync: true }), {
        global: {
            mocks: {
                $t: jest.fn((key, params) => `${params.value}% discount on shopping cart`),
            },
            stubs: {
                'sw-block-field': {
                    template: '<div><slot name="sw-field-input" v-bind="slotProps" /></div>',
                    data() {
                        return {
                            slotProps: {
                                identification: 'promotion-tag-field',
                                error: null,
                                disabled: false,
                                size: 'default',
                                setFocusClass: () => {},
                                removeFocusClass: () => {},
                            },
                        };
                    },
                },
                'sw-label': true,
            },
        },
        props: {
            currency: {
                isoCode: 'EUR',
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-promotion-tag-field', () => {
    it('should translate promotion descriptions with interpolation values', async () => {
        const wrapper = await createWrapper();

        const description = wrapper.vm.getPromotionCodeDescription({
            discountId: 'promotion-discount-id',
            value: 10,
            discountScope: 'cart',
            discountType: 'percentage',
            groupId: 'set-group-id',
        });

        expect(wrapper.vm.$t).toHaveBeenCalledWith(
            'sw-order.createBase.textPromotionDescription.cart.percentage',
            {
                value: 10,
                groupId: 'set-group-id',
            },
        );
        expect(description).toBe('10% discount on shopping cart');
    });

    it('should use the promotion code as description when the item has no discount', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getPromotionCodeDescription({ code: 'SUMMER-SALE' })).toBe('SUMMER-SALE');
    });
});
