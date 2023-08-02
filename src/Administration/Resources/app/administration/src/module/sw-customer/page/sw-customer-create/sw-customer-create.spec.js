import { shallowMount } from '@vue/test-utils';
import swCustomerCreate from 'src/module/sw-customer/page/sw-customer-create';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';

/**
 * @package customer-order
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-customer-create', swCustomerCreate);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-customer-create'), {
        stubs: {
            'sw-page': true,
            'sw-card': true,
            'sw-language-switch': true,
            'sw-customer-address-form': true,
            'sw-customer-base-form': true,
            'sw-card-view': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-process': await Shopware.Component.build('sw-button-process'),
            'sw-icon': true,
            'sw-loader': true,
        },
        provide: {
            numberRangeService: {},
            systemConfigApiService: {
                getValues: () => Promise.resolve({ 'core.register.minPasswordLength': 8 }),
            },
            customerValidationService: {},
            repositoryFactory: {
                create: (entity) => {
                    if (entity === 'customer') {
                        return {
                            create: () => {
                                return {
                                    id: '63e27affb5804538b5b06cb4e344b130',
                                    addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                                };
                            },
                        };
                    }

                    if (entity === 'language') {
                        return {
                            searchIds: () => Promise.resolve({
                                total: 1,
                                data: ['1'],
                            }),
                        };
                    }

                    if (entity === 'salutation') {
                        return {
                            searchIds: () => Promise.resolve({
                                total: 1,
                                data: ['salutationId'],
                            }),
                        };
                    }

                    return {
                        create: () => Promise.resolve(),
                    };
                },
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-create', () => {
    it('should have valid email validation response when no email is given', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            customer: {
                id: '1',
                email: null,
                boundSalesChannelId: null,
            },
        });

        const response = await wrapper.vm.validateEmail();

        expect(response.isValid).toBe(true);
    });

    it('should have company validation when customer type is commercial', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));
        const notificationMock = wrapper.vm.createNotificationError;

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                password: 'shopware',
            },
            address: {
                company: '',
            },
        });
        const saveButton = wrapper.find('.sw-customer-create__save-action');
        await saveButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(notificationMock).toHaveBeenCalledTimes(2);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-customer.detail.messageSaveError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should override context when the sales channel does not exist language compared to the API language', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));
        wrapper.vm.customerRepository.save = jest.fn((customer, context) => Promise.resolve(context));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                password: 'shopware',
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
            },
            address: {
                company: 'Shopware',
            },
        });

        expect(await wrapper.vm.languageId).toBe('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toBe('1');
    });

    it('should keep context when sales channel exists language compared to API language', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));
        wrapper.vm.customerRepository.save = jest.fn((customer, context) => Promise.resolve(context));

        wrapper.vm.languageRepository.searchIds = jest.fn(() => Promise.resolve({
            total: 1,
            data: [Shopware.Context.api.languageId],
        }));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                password: 'shopware',
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
            },
            address: {
                company: 'Shopware',
            },
        });

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toEqual(Shopware.Context.api.languageId);
    });

    it('should get default salutation is value not specified', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.customer.salutationId).toBe('salutationId');
        expect(wrapper.vm.address.salutationId).toBe('salutationId');
    });

    it('should not render sw-customer-base-form and sw-customer-address-form if customer is null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            customer: null,
        });

        expect(wrapper.find('sw-customer-base-form-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-customer-address-form-stub').exists()).toBeFalsy();
    });
});
