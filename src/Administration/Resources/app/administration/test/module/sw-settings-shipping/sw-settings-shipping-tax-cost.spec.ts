import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-shipping/component/sw-settings-shipping-tax-cost';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

Shopware.State.registerModule('swShippingDetail', state);

const createWrapper = () => {
    beforeEach(() => {
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            taxType: null,
        });
    });

    return shallowMount(Shopware.Component.build('sw-settings-shipping-tax-cost'), {
        stubs: {
            'sw-card': true,
            'sw-single-select': true,
            'sw-entity-single-select': true,
        },
        propsData: {
        }
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-tax-cost', () => {
    it('should put tax type to auto for new shipping methods', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toEqual('auto');
    });

    it('should use tax of shipping method if defined', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            taxType: 'fixed',
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxType).toEqual('fixed');
    });
});
