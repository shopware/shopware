import { shallowMount, enableAutoDestroy } from '@vue/test-utils';

import 'src/module/sw-order/component/sw-order-custom-item';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-custom-item'), {
        propsData: {
            taxStatus: 'gross',
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            customItem: {}
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-entity-single-select': true,
            'sw-number-field': true,
            'sw-text-field': true,
        },
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-order/view/sw-order-custom-item', () => {
    it('should price label and placeholder correctly', async () => {
        const wrapper = createWrapper();

        const priceField = wrapper.find('.sw-order-custom-item__price');
        expect(priceField.attributes().label).toEqual('sw-order.createBase.columnPriceGross');
        expect(priceField.attributes().placeholder).toEqual('sw-order.itemModal.customItem.placeholderPriceGross');

        await wrapper.setProps({
            taxStatus: 'net'
        });

        expect(priceField.attributes().label).toEqual('sw-order.createBase.columnPriceNet');
        expect(priceField.attributes().placeholder).toEqual('sw-order.itemModal.customItem.placeholderPriceNet');
    });
});
