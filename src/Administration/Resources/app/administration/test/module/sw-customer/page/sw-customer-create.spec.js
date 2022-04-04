import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-create';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-customer-create'), {
        stubs: {
            'sw-page': true,
            'sw-card': true,
            'sw-language-switch': true,
            'sw-customer-address-form': true,
            'sw-customer-base-form': true,
            'sw-card-view': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-icon': true
        },
        provide: {
            numberRangeService: {},
            systemConfigApiService: {},
            customerValidationService: {},
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

    it('should have company validation when customer type is commercial', async () => {
        const wrapper = createWrapper();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));
        const notificationMock = wrapper.vm.createNotificationError;

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business'
            },
            address: {
                company: ''
            }
        });
        const saveButton = wrapper.find('.sw-customer-create__save-action');
        await saveButton.trigger('click');

        expect(notificationMock).toBeCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-customer.error.COMPANY_IS_REQUIRED'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });
});
