import { shallowMount } from '@vue/test-utils';
import swSettingsShippingPriceMatrix from 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

/**
 * @package checkout
 */
Shopware.State.registerModule('swShippingDetail', state);
Shopware.Component.register('sw-settings-shipping-price-matrix', swSettingsShippingPriceMatrix);

const createWrapper = async () => {
    return shallowMount(await Shopware.Component.build('sw-settings-shipping-price-matrix'), {
        store: Shopware.State._store,
        stubs: {
            'sw-card': true,
            'sw-container': true,
            'sw-select-rule-create': true,
            'sw-button': true,
            'sw-context-button': true,
            'sw-data-grid': true,
            'sw-context-menu-item': true,
        },
        propsData: {
            priceGroup: {
                isNew: false,
                ruleId: 'ruleId',
                rule: {},
                calculation: 1,
                prices: [{
                    _isNew: true,
                    shippingMethodId: 'shippingMethodId',
                    quantityStart: 1,
                    ruleId: 'ruleId',
                    rule: {},
                    calculation: 1,
                    currencyPrice: [{ currencyId: 'euro', gross: 0, linked: false, net: 0 }],
                }],
            },
        },
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrix', () => {
    beforeEach(async () => {
        Shopware.State.commit('swShippingDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } },
        ]);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();
        const ruleFilterCriteria = wrapper.vm.ruleFilterCriteria;
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(ruleFilterCriteria.associations[0].association).toBe('conditions');
        expect(shippingRuleFilterCriteria.associations[0].association).toBe('conditions');
    });
});
