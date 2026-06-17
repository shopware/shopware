import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-order-promotion-tag-field', { sync: true }), {
        global: {
            mocks: {
                $t: jest.fn((key, params) => `${params.value} discount on shopping cart`),
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
            value: [],
            currency: {
                isoCode: 'EUR',
            },
            ...props,
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

        expect(wrapper.vm.$t).toHaveBeenCalledWith('sw-order.createBase.textPromotionDescription.cart.percentage', {
            value: 10,
            groupId: 'set-group-id',
        });
        expect(description).toBe('10 discount on shopping cart');
    });

    it('should translate absolute promotion descriptions with formatted currency values', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getPromotionCodeDescription({
            discountId: 'promotion-discount-id',
            value: 10,
            discountScope: 'cart',
            discountType: 'absolute',
            groupId: 'set-group-id',
        });

        expect(wrapper.vm.$t).toHaveBeenCalledWith('sw-order.createBase.textPromotionDescription.cart.absolute', {
            value: expect.stringContaining('10'),
            groupId: 'set-group-id',
        });
    });

    it('should use the promotion code as description when the item has no discount', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getPromotionCodeDescription({ code: 'SUMMER-SALE' })).toBe('SUMMER-SALE');
    });

    it('should add a promotion code tag', async () => {
        const wrapper = await createWrapper({
            value: [
                { code: 'EXISTING-CODE' },
            ],
        });
        const event = {
            key: 'Enter',
            preventDefault: jest.fn(),
        };

        await wrapper.setData({
            newTagName: 'SUMMER-SALE',
        });

        wrapper.vm.performAddTag(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(wrapper.emitted('update:value')).toEqual([
            [
                [
                    { code: 'EXISTING-CODE' },
                    { code: 'SUMMER-SALE' },
                ],
            ],
        ]);
        expect(wrapper.vm.newTagName).toBe('');
    });

    it('should not add a promotion code tag when the code already exists', async () => {
        const wrapper = await createWrapper({
            value: [
                { code: 'SUMMER-SALE' },
            ],
        });

        await wrapper.setData({
            newTagName: 'SUMMER-SALE',
        });

        wrapper.vm.performAddTag({
            key: 'Enter',
            preventDefault: jest.fn(),
        });

        expect(wrapper.emitted('update:value')).toBeUndefined();
        expect(wrapper.vm.newTagName).toBe('SUMMER-SALE');
    });

    it('should not add a promotion code tag when the trigger key does not match', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            newTagName: 'SUMMER-SALE',
        });

        wrapper.vm.performAddTag({
            key: 'Escape',
            preventDefault: jest.fn(),
        });

        expect(wrapper.emitted('update:value')).toBeUndefined();
        expect(wrapper.vm.newTagName).toBe('SUMMER-SALE');
    });

    it('should not add a promotion code tag when the field is disabled', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        await wrapper.setData({
            newTagName: 'SUMMER-SALE',
        });

        wrapper.vm.performAddTag({
            key: 'Enter',
            preventDefault: jest.fn(),
        });

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should emit the removed promotion code tag', async () => {
        const wrapper = await createWrapper();
        const item = { code: 'SUMMER-SALE' };

        wrapper.vm.dismissTag(item);

        expect(wrapper.emitted('on-remove-code')).toEqual([
            [item],
        ]);
    });

    it('should focus the input when the field receives focus', async () => {
        const wrapper = await createWrapper();
        const focus = jest.spyOn(wrapper.vm.$refs.taggedFieldInput, 'focus');

        wrapper.vm.setFocus(true);

        expect(wrapper.vm.hasFocus).toBe(true);
        expect(focus).toHaveBeenCalled();
    });

    it('should not focus the input when the field is disabled', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });
        const focus = jest.spyOn(wrapper.vm.$refs.taggedFieldInput, 'focus');

        wrapper.vm.setFocus(true);

        expect(wrapper.vm.hasFocus).toBe(false);
        expect(focus).not.toHaveBeenCalled();
    });

    it('should add disabled styling for the tag list', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        expect(wrapper.vm.taggedFieldListClasses).toEqual({
            'sw-tagged-field__tag-list--disabled': true,
        });
    });
});
