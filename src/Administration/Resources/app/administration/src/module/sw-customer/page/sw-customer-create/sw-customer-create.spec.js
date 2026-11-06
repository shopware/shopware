import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper({
    customerRepositorySaveMock,
    languageRepositorySearchIdsMock,
    isContactPersonRequired = () => Promise.resolve(true),
} = {}) {
    return mount(await wrapTestComponent('sw-customer-create', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-language-switch': true,
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-loader': true,
                'router-link': true,
            },
            provide: {
                numberRangeService: {},
                systemConfigApiService: {
                    getValues: () =>
                        Promise.resolve({
                            'core.register.minPasswordLength': 8,
                        }),
                },
                companyAccountNameFieldsService: {
                    isContactPersonRequired,
                },
                customerValidationService: {
                    checkCustomerEmail: () => Promise.resolve({ isValid: true }),
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'customer') {
                            return {
                                create: () => {
                                    return {
                                        id: '63e27affb5804538b5b06cb4e344b130',
                                        addresses: new EntityCollection(
                                            '/customer_address',
                                            'customer_address',
                                            Context.api,
                                            null,
                                            [],
                                        ),
                                    };
                                },
                                save: customerRepositorySaveMock,
                            };
                        }

                        if (entity === 'language') {
                            return {
                                searchIds:
                                    languageRepositorySearchIdsMock ??
                                    (() =>
                                        Promise.resolve({
                                            total: 1,
                                            data: ['1'],
                                        })),
                            };
                        }

                        if (entity === 'salutation') {
                            return {
                                searchIds: () =>
                                    Promise.resolve({
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
        },
    });
}

async function clickSave(wrapper) {
    await wrapper.find('.sw-customer-create__save-action').trigger('click');
    await flushPromises();
}

describe('module/sw-customer/page/sw-customer-create', () => {
    it('is strict again while the settings of the next sales channel are read', async () => {
        let resolveNextSalesChannel;
        const nextSalesChannelRequirement = new Promise((resolve) => {
            resolveNextSalesChannel = resolve;
        });
        const isContactPersonRequired = (salesChannelId) => {
            return salesChannelId ? nextSalesChannelRequirement : Promise.resolve(false);
        };
        const wrapper = await createWrapper({ isContactPersonRequired });
        await flushPromises();

        const companyNamesRequired = () => {
            return wrapper.find('sw-customer-base-form-stub').attributes('company-names-required');
        };
        expect(companyNamesRequired()).toBe('false');

        await wrapper.setData({ customer: { salesChannelId: 'a7921464677a4ef591683d144beecd24' } });

        expect(companyNamesRequired()).toBe('true');

        resolveNextSalesChannel(false);
        await flushPromises();

        expect(companyNamesRequired()).toBe('false');
    });

    it('should have valid email validation response when no email is given', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
        await flushPromises();

        expect(notificationMock).toHaveBeenCalledTimes(2);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-customer.detail.messageSaveError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should override context when the sales channel does not exist language compared to the API language', async () => {
        const customerRepositorySaveMock = jest.fn((customer, context) => Promise.resolve(context));
        const wrapper = await createWrapper({ customerRepositorySaveMock });
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                firstName: 'Ada',
                lastName: 'Lovelace',
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
        const customerRepositorySaveMock = jest.fn((customer, context) => Promise.resolve(context));
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 1,
                data: [Shopware.Context.api.languageId],
            }),
        );

        const wrapper = await createWrapper({ customerRepositorySaveMock, languageRepositorySearchIdsMock });
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                firstName: 'Ada',
                lastName: 'Lovelace',
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

    it('should default the customer language to the API context language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.customer.languageId).toBe(Shopware.Context.api.languageId);
    });

    it('should update the customer language when the sales channel changes', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 1,
                data: ['salesChannelLanguageId'],
            }),
        );
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await wrapper.setData({
            customer: {
                ...wrapper.vm.customer,
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
            },
        });
        await flushPromises();

        expect(wrapper.vm.customer.languageId).toBe('salesChannelLanguageId');
    });

    it('should fall back to the API context language when the sales channel has no matching language', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 0,
                data: [],
            }),
        );
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await wrapper.setData({
            customer: {
                ...wrapper.vm.customer,
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
            },
        });
        await flushPromises();

        expect(wrapper.vm.customer.languageId).toBe(Shopware.Context.api.languageId);
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

    it('should throw exception when the customer creation fails', async () => {
        const customerRepositorySaveMock = jest.fn(() =>
            Promise.reject({
                response: {
                    data: {
                        errors: [
                            {
                                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                                detail: 'This value should not be blank.',
                                status: '400',
                                template: 'This value should not be blank.',
                            },
                        ],
                    },
                },
            }),
        );

        const wrapper = await createWrapper({ customerRepositorySaveMock });
        await wrapper.setData({
            customer: {
                id: '1',
                email: 'ytn@shopware.com',
                firstName: 'Ada',
                lastName: 'Lovelace',
                boundSalesChannelId: null,
            },
        });

        try {
            await wrapper.vm.onSave();
        } catch (e) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(e.response.data.errors[0]).toEqual({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            });
        }
    });
    it.each([
        [
            'the account company wins when both are set',
            'Acme GmbH',
            'Old GmbH',
            'Acme GmbH',
        ],
        [
            'a cleared account company falls back to the address',
            '',
            'Addr GmbH',
            'Addr GmbH',
        ],
        [
            'a blank account company falls back to the address',
            '   ',
            'Addr GmbH',
            'Addr GmbH',
        ],
    ])('should resolve the company: %s', async (_name, company, addressCompany, expected) => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ customerRepositorySaveMock });
        await flushPromises();

        await wrapper.setData({
            customer: { accountType: 'business', company, firstName: 'Ada', lastName: 'Lovelace' },
            address: { company: addressCompany },
        });
        await clickSave(wrapper);

        const [savedCustomer] = customerRepositorySaveMock.mock.calls[0];
        expect(savedCustomer.company).toBe(expected);
    });

    it('should mark both company fields when a company account has none', async () => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ customerRepositorySaveMock });
        await flushPromises();

        await wrapper.setData({
            customer: { id: '1', accountType: 'business', firstName: 'Ada', lastName: 'Lovelace' },
            address: { id: '2' },
        });
        await clickSave(wrapper);

        expect(customerRepositorySaveMock).not.toHaveBeenCalled();

        const errors = Shopware.Store.get('error');

        expect(errors.getApiError({ getEntityName: () => 'customer', id: '1' }, 'company')).not.toBeNull();
        expect(errors.getApiError({ getEntityName: () => 'customer_address', id: '2' }, 'company')).not.toBeNull();
    });

    it('should refuse to save a company account without a contact person while the settings require one', async () => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ customerRepositorySaveMock });
        await flushPromises();

        await wrapper.setData({
            customer: { accountType: 'business' },
            address: { company: 'Acme GmbH' },
        });
        await clickSave(wrapper);

        expect(customerRepositorySaveMock).not.toHaveBeenCalled();
    });

    it('should save a company account without a contact person once the settings allow it', async () => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({
            customerRepositorySaveMock,
            isContactPersonRequired: () => Promise.resolve(false),
        });
        await flushPromises();

        await wrapper.setData({
            customer: { accountType: 'business' },
            address: { company: 'Acme GmbH' },
        });
        await clickSave(wrapper);

        expect(customerRepositorySaveMock).toHaveBeenCalledTimes(1);

        const [savedCustomer] = customerRepositorySaveMock.mock.calls[0];
        expect(savedCustomer.firstName).toBe('');
        expect(savedCustomer.lastName).toBe('');
    });

    it('should still require a contact person on a private account', async () => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({
            customerRepositorySaveMock,
            isContactPersonRequired: () => Promise.resolve(false),
        });
        await flushPromises();

        await wrapper.setData({ customer: { accountType: 'private' } });
        await clickSave(wrapper);

        expect(customerRepositorySaveMock).not.toHaveBeenCalled();
    });
});
