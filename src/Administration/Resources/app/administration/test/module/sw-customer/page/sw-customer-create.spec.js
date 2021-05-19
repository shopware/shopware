import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-create';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-customer-create'), {
        stubs: {
            'sw-page': true,
            'sw-button': true,
            'sw-card': true,
            'sw-language-switch': true,
            'sw-customer-address-form': true,
            'sw-customer-base-form': true,
            'sw-card-view': true,
            'sw-button-process': true
        },
        provide: {
            numberRangeService: {},
            systemConfigApiService: {},
            customerValidationService: {}
        }
    });
}

describe('module/sw-customer/page/sw-customer-create', () => {
    it('should have valid email validation response when no email is given', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.setData({
            customer: {
                id: '1',
                email: null,
                boundSalesChannelId: null
            }
        });

        const response = await wrapper.vm.validateEmail();

        expect(response.isValid).toBe(true);
    });
});
