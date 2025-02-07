import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const createWrapper = async () => {
    return mount(
        await wrapTestComponent('sw-settings-shipping-tax-cost', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-entity-single-select': true,
                    'sw-single-select': true,
                },
            },
        },
    );
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-tax-cost', () => {
    beforeEach(() => {
        Shopware.Store.get('swShippingDetail').shippingMethod = { taxType: null };
    });

    it('should put tax type to auto for new shipping methods', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toBe('auto');
    });

    it('should use tax of shipping method if defined', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swShippingDetail').shippingMethod = {
            taxType: 'fixed',
        };

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toBe('fixed');
    });
});
