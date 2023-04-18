import { createLocalVue, shallowMount } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';
import swOrderCreateDetailsFooter from 'src/module/sw-order/component/sw-order-create-details-footer';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-create-details-footer', swOrderCreateDetailsFooter);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-create-details-footer'), {
        localVue,
        stubs: {
            'sw-container': true,
            'sw-entity-single-select': true,
        },
        propsData: {
            customer: {
                salesChannelId: '98432def39fc4624b33213a56b8c944d',
                salesChannel: {
                    paymentMethodId: null,
                },
            },
            cart: {},
            isCustomerActive: true,
        },
    });
}

describe('src/module/sw-order/component/sw-order-create-details-footer', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('paymentMethodCriteria should filter for afterOrderEnabled payment methods', async () => {
        const paymentMethodCriteria = wrapper.vm.paymentMethodCriteria;
        expect(paymentMethodCriteria).toBeInstanceOf(Criteria);
        expect(paymentMethodCriteria.filters).toBeInstanceOf(Array);

        const afterOrderEnabledFilter = paymentMethodCriteria.filters.find(filter => filter.field === 'afterOrderEnabled');
        expect(afterOrderEnabledFilter).toBeDefined();
        expect(afterOrderEnabledFilter.type).toBe('equals');
        expect(afterOrderEnabledFilter.value).toBe(1);
    });
});
