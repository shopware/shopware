import { shallowMount } from '@vue/test-utils';
import swPriceRuleModal from 'src/module/sw-settings-shipping/component/sw-price-rule-modal';

/**
 * @package checkout
 */

Shopware.Component.register('sw-price-rule-modal', swPriceRuleModal);


async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-price-rule-modal'));
}

describe('module/sw-settings-shipping/component/sw-price-rule-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});

