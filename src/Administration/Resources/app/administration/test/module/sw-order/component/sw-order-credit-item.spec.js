import { shallowMount, enableAutoDestroy } from '@vue/test-utils';

import 'src/module/sw-order/component/sw-order-credit-item';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-credit-item'), {
        propsData: {
            taxStatus: 'gross',
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            credit: {}
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-number-field': {
                model: {
                    prop: 'value',
                    event: 'change'
                },
                template: '<input class="sw-number-field" type="number" :value="value" @change="$emit(\'change\', $event.target.value)"/>',
                props: {
                    value: 0
                }
            },
            'sw-text-field': true,
        },
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-order/view/sw-order-credit-item', () => {
    it('should convert credit to negative value', async () => {
        const wrapper = createWrapper();
        const priceField = wrapper.find('.sw-order-credit-item__price');

        await priceField.setValue(100);
        await priceField.trigger('change');

        expect(wrapper.vm.credit.price).toEqual(-100);
    });
});
