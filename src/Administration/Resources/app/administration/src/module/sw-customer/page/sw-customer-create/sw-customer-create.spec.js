import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper({
    customerRepositorySaveMock = jest.fn(() => Promise.resolve()),
    languageRepositorySearchIdsMock = jest.fn(() => Promise.resolve({ total: 1, data: ['1'] })),
    salutationRepositorySearchIdsMock = jest.fn(() => Promise.resolve({ total: 1, data: ['salutationId'] })),
    checkCustomerEmail = jest.fn(() => Promise.resolve({ isValid: true })),
    getCustomFieldSets = jest.fn(() => Promise.resolve([])),
    customer = {},
    address = {},
    errorHandler,
} = {}) {
    return mount(await wrapTestComponent('sw-customer-create', { sync: true }), {
        global: {
            config: { errorHandler },
            mocks: {
                $router: { push: jest.fn() },
            },
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-language-switch': true,
                'sw-customer-address-form': {
                    name: 'sw-customer-address-form',
                    props: ['customer', 'address'],
                    template: '<div />',
                },
                'sw-customer-base-form': {
                    name: 'sw-customer-base-form',
                    props: ['customer', 'isLoading'],
                    emits: ['sales-channel-change'],
                    template: `
                        <select aria-label="Account type" v-model="customer.accountType">
                            <option value="private">Private</option>
                            <option value="business">Business</option>
                        </select>`,
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-loader': true,
                'router-link': true,
                'sw-custom-field-set-renderer': {
                    name: 'sw-custom-field-set-renderer',
                    props: ['entity', 'sets'],
                    template: '<div />',
                },
            },
            provide: {
                numberRangeService: { reserve: jest.fn(() => Promise.resolve({ number: '10001' })) },
                systemConfigApiService: {
                    getValues: () =>
                        Promise.resolve({
                            'core.register.minPasswordLength': 8,
                        }),
                },
                customerValidationService: { checkCustomerEmail },
                customFieldDataProviderService: {
                    getCustomFieldSets,
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'customer') {
                            return {
                                create: () => {
                                    return {
                                        id: '63e27affb5804538b5b06cb4e344b130',
                                        ...customer,
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
                                searchIds: languageRepositorySearchIdsMock,
                            };
                        }

                        if (entity === 'salutation') {
                            return {
                                searchIds: salutationRepositorySearchIdsMock,
                            };
                        }

                        return {
                            create: () => ({ id: 'address-id', ...address }),
                        };
                    },
                },
            },
        },
    });
}

async function saveCustomer(wrapper) {
    await wrapper.get('.sw-customer-create__save-action').trigger('click');
    await flushPromises();
}

async function changeSalesChannel(wrapper) {
    await wrapper
        .findComponent({ name: 'sw-customer-base-form' })
        .vm.$emit('sales-channel-change', 'a7921464677a4ef591683d144beecd24');
    await flushPromises();
}

function customerForm(wrapper) {
    return wrapper.findComponent({ name: 'sw-customer-base-form' });
}

describe('module/sw-customer/page/sw-customer-create', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should save without checking email when no email is given', async () => {
        const checkCustomerEmail = jest.fn();
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ checkCustomerEmail, customerRepositorySaveMock });
        await flushPromises();

        await saveCustomer(wrapper);

        expect(checkCustomerEmail).not.toHaveBeenCalled();
        expect(customerRepositorySaveMock).toHaveBeenCalledTimes(1);
    });

    it('should show company validation errors when a business customer has no company', async () => {
        const notificationMock = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        const customerRepositorySaveMock = jest.fn();
        const checkCustomerEmail = jest.fn(() => Promise.resolve({ isValid: true }));
        const wrapper = await createWrapper({
            customerRepositorySaveMock,
            checkCustomerEmail,
            customer: { email: 'user@domain.com' },
            address: { company: '' },
        });
        await flushPromises();
        await wrapper.get('[aria-label="Account type"]').setValue('business');

        await saveCustomer(wrapper);

        expect(checkCustomerEmail).toHaveBeenCalledWith({
            id: '63e27affb5804538b5b06cb4e344b130',
            email: 'user@domain.com',
            boundSalesChannelId: undefined,
        });
        expect(customerRepositorySaveMock).not.toHaveBeenCalled();
        expect(notificationMock).toHaveBeenCalledTimes(2);
        expect(notificationMock).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-customer.error.COMPANY_IS_REQUIRED',
            }),
        );
        expect(notificationMock).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-customer.detail.messageSaveError',
            }),
        );
        expect(wrapper.get('.sw-customer-create__save-action').attributes('disabled')).toBeUndefined();
    });

    it.each([
        ['the sales channel language', 'salesChannelLanguageId'],
        ['the API language when supported by the sales channel', Shopware.Context.api.languageId],
    ])('should save using %s', async (_, languageId) => {
        const customerRepositorySaveMock = jest.fn(() => Promise.resolve());
        const languageRepositorySearchIdsMock = jest.fn(() => Promise.resolve({ total: 1, data: [languageId] }));
        const wrapper = await createWrapper({ customerRepositorySaveMock, languageRepositorySearchIdsMock });
        await flushPromises();

        await changeSalesChannel(wrapper);
        await saveCustomer(wrapper);

        expect(customerRepositorySaveMock).toHaveBeenCalledWith(
            expect.objectContaining({ languageId, salesChannelId: 'a7921464677a4ef591683d144beecd24' }),
            expect.objectContaining({ languageId }),
        );
    });

    it('should default the customer language to the API context language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(customerForm(wrapper).props('customer').languageId).toBe(Shopware.Context.api.languageId);
    });

    it('should update the customer language when the sales channel changes', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({ total: 1, data: ['salesChannelLanguageId'] }),
        );
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await changeSalesChannel(wrapper);

        expect(customerForm(wrapper).props('customer').languageId).toBe('salesChannelLanguageId');
    });

    it('should fall back to the API context language when the sales channel has no matching language', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() => Promise.resolve({ total: 0, data: [] }));
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await changeSalesChannel(wrapper);

        expect(customerForm(wrapper).props('customer').languageId).toBe(Shopware.Context.api.languageId);
    });

    it('should pass the default salutation to the customer and address forms', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(customerForm(wrapper).props('customer').salutationId).toBe('salutationId');
        expect(wrapper.findComponent({ name: 'sw-customer-address-form' }).props('address').salutationId).toBe(
            'salutationId',
        );
    });

    it('should wait for customer initialization before rendering the forms', async () => {
        let resolveSalutation;
        const salutationRepositorySearchIdsMock = jest.fn(
            () =>
                new Promise((resolve) => {
                    resolveSalutation = resolve;
                }),
        );
        const wrapper = await createWrapper({ salutationRepositorySearchIdsMock });

        expect(customerForm(wrapper).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-customer-address-form' }).exists()).toBe(false);

        resolveSalutation({ total: 1, data: ['salutationId'] });
        await flushPromises();

        expect(customerForm(wrapper).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-customer-address-form' }).exists()).toBe(true);
    });

    it('should show a notification and propagate the error when customer creation fails', async () => {
        const error = {
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
        };
        const customerRepositorySaveMock = jest.fn(() => Promise.reject(error));
        const notificationMock = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        const errorHandler = jest.fn();
        const wrapper = await createWrapper({ customerRepositorySaveMock, errorHandler });
        await flushPromises();

        await saveCustomer(wrapper);

        expect(customerRepositorySaveMock).toHaveBeenCalledTimes(1);
        expect(errorHandler).toHaveBeenCalledWith(error, expect.anything(), expect.any(String));
        expect(notificationMock).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-customer.detail.messageSaveError',
            }),
        );
        expect(wrapper.get('.sw-customer-create__save-action').attributes('disabled')).toBeUndefined();
    });

    it('should not render the custom field card when there are no customer custom field sets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-customer-create__custom-field-sets').exists()).toBe(false);
    });

    it('should render the customer custom field sets for the new customer', async () => {
        const customFieldSets = [{ id: 'set-1', name: 'customer_set', customFields: [{ id: 'field-1' }] }];
        const getCustomFieldSets = jest.fn(() => Promise.resolve(customFieldSets));
        const wrapper = await createWrapper({ getCustomFieldSets });
        await flushPromises();

        expect(getCustomFieldSets).toHaveBeenCalledWith('customer');
        const renderer = wrapper.findComponent({ name: 'sw-custom-field-set-renderer' });
        expect(wrapper.find('.sw-customer-create__custom-field-sets').exists()).toBe(true);
        expect(renderer.props('sets')).toEqual(customFieldSets);
        expect(renderer.props('entity')).toEqual(customerForm(wrapper).props('customer'));
    });
});
