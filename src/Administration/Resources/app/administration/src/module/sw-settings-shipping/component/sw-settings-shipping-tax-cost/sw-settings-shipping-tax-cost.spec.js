import { mount } from '@vue/test-utils';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

/**
 * @package checkout
 */

Shopware.State.registerModule('swShippingDetail', state);

const createWrapper = async () => {
    return mount(await wrapTestComponent('sw-settings-shipping-tax-cost', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-card': true,
                'sw-entity-single-select': true,
                'sw-single-select': true,
            },
        },
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-tax-cost', () => {
    beforeEach(() => {
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            taxType: null,
        });
    });

    it('should put tax type to auto for new shipping methods', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toBe('auto');
    });

    it('should use tax of shipping method if defined', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            taxType: 'fixed',
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toBe('fixed');
    });
});
