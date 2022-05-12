import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

Shopware.State.registerModule('swShippingDetail', state);

const ruleConditionDataProviderServiceMock = {};

const createWrapper = () => {
    return shallowMount(Shopware.Component.build('sw-settings-shipping-price-matrix'), {
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
                    currencyPrice: [{ currencyId: 'euro', gross: 0, linked: false, net: 0 }]
                }]
            }
        },
        provide: {
            ruleConditionDataProviderService: ruleConditionDataProviderServiceMock,
        }
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrix', () => {
    beforeEach(() => {
        Shopware.State.commit('swShippingDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } }
        ]);
    });
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a restriction tooltip when rule is restricted', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_18215'];
        const wrapper = createWrapper();
        ruleConditionDataProviderServiceMock.isRuleRestricted = jest.fn(() => true);
        ruleConditionDataProviderServiceMock.getRestrictedRuleTooltipConfig = jest.fn(() => true);

        const shippingMethodTooltip = wrapper.vm.shippingMethodRuleTooltipConfig({}, false, 'someKey');
        const shippingMethodPriceTooltip = wrapper.vm.shippingPriceRuleTooltipConfig({}, false, 'someKey');

        expect(shippingMethodTooltip).toBeTruthy();
        expect(shippingMethodPriceTooltip).toBeTruthy();
    });

    it('should have a in use tooltip when rule is not restricted', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_18215'];
        const wrapper = createWrapper();
        ruleConditionDataProviderServiceMock.isRuleRestricted = jest.fn(() => false);

        const shippingMethodTooltip = wrapper.vm.shippingMethodRuleTooltipConfig({}, false, 'someKey');
        const shippingMethodPriceTooltip = wrapper.vm.shippingPriceRuleTooltipConfig({}, false, 'someKey');

        expect(shippingMethodTooltip.message).toEqual('sw-settings-shipping.priceMatrix.ruleAlreadyUsed');
        expect(shippingMethodPriceTooltip.message).toEqual('sw-settings-shipping.priceMatrix.ruleAlreadyUsedInMatrix');
    });

    it('should add conditions association', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_18215'];
        const wrapper = createWrapper();
        const ruleFilterCriteria = wrapper.vm.ruleFilterCriteria;
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(ruleFilterCriteria.associations[0].association).toEqual('conditions');
        expect(shippingRuleFilterCriteria.associations[0].association).toEqual('conditions');
    });

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_18215) test can be removed
     */
    it('should not be restricted if feature is off', async () => {
        global.activeFeatureFlags = [];
        const wrapper = createWrapper();
        const restricted = wrapper.vm.isRuleRestricted();

        expect(restricted).toBeFalsy();
    });
});
