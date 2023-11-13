import { shallowMount } from '@vue/test-utils';
import swSettingsShippingTaxCost from 'src/module/sw-settings-shipping/component/sw-settings-shipping-tax-cost';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

/**
 * @package checkout
 */

Shopware.State.registerModule('swShippingDetail', state);
Shopware.Component.register('sw-settings-shipping-tax-cost', swSettingsShippingTaxCost);

const createWrapper = async () => {
    return shallowMount(await Shopware.Component.build('sw-settings-shipping-tax-cost'), {
        stubs: {
            'sw-card': true,
            'sw-single-select': true,
            'sw-entity-single-select': true,
        },
        propsData: {
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
