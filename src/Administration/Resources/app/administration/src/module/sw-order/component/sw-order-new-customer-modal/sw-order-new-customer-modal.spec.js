import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

/**
 * @sw-package checkout
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(
    repositoryMocks = {
        customerRepositoryMock: undefined,
        languageRepositoryMock: undefined,
    },
) {
    return mount(await wrapTestComponent('sw-order-new-customer-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'mt-tabs': {
                    props: [
                        'items',
                        'defaultItem',
                        'positionIdentifier',
                    ],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                },
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'customer') {
                            if (repositoryMocks.customerRepositoryMock) {
                                return repositoryMocks.customerRepositoryMock;
                            }

                            return {
                                create: () => {
                                    return {
                                        id: '1',
                                        addresses: new EntityCollection(
                                            '/customer_address',
                                            'customer_address',
                                            Context.api,
                                            null,
                                            [],
                                        ),
                                    };
                                },
                                save: () => Promise.resolve(),
                            };
                        }

                        if (entity === 'language') {
                            if (repositoryMocks.languageRepositoryMock) {
                                return repositoryMocks.languageRepositoryMock;
                            }

                            return {
                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['1'],
                                    }),
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

                        if (entity === 'customer_address') {
                            return {
                                create: () => {
                                    return {
                                        id: 'new-shipping-address-id',
                                    };
                                },
                            };
                        }

                        return {
                            create: () => Promise.resolve(),
                        };
                    },
                },
                numberRangeService: {
                    reverse: () => Promise.resolve(),
                },
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({
                            'core.loginRegistration.passwordMinLength': 8,
                        });
                    },
                },
                customerValidationService: {
                    checkCustomerEmail: () => Promise.resolve(),
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-new-customer-modal', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeFeatureFlags = [''];
        wrapper = await createWrapper();
    });

    it('should render legacy tabs when the major feature flag is inactive', async () => {
        await flushPromises();

        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper();
        await flushPromises();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-order.newCustomerModal.labelDetails',
                name: 'details',
                hasError: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.createBase.detailsBody.labelBillingAddress',
                name: 'billingAddress',
                hasError: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.createBase.detailsBody.labelShippingAddress',
                name: 'shippingAddress',
                hasError: false,
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('details');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-order-new-customer-modal');
        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(false);
    });

    it('should switch active content in the meteor tabs branch', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.getComponent('mt-tabs-stub').props('items')[1].onClick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('billingAddress');
        expect(wrapper.find('sw-customer-base-form-stub').exists()).toBe(false);
        expect(wrapper.find('sw-customer-address-form-stub').exists()).toBe(true);
    });

    it('should navigate tab correctly', async () => {
        let customerBaseForm = wrapper.find('sw-customer-base-form-stub');
        let customerAddressForm = wrapper.find('sw-customer-address-form-stub');

        expect(customerBaseForm.exists()).toBeTruthy();
        expect(customerAddressForm.exists()).toBeFalsy();

        const tabItems = wrapper.findAll('.sw-tabs-item');
        await tabItems.at(1).trigger('click');

        customerBaseForm = wrapper.find('sw-customer-base-form-stub');
        customerAddressForm = wrapper.find('sw-customer-address-form-stub');

        expect(customerBaseForm.exists()).toBeFalsy();
        expect(customerAddressForm.exists()).toBeTruthy();
    });

    it('should override context when the sales channel does not exist language compared to the API language', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            customerRepositoryMock: {
                create: () => ({
                    id: '1',
                    addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                }),
                save: jest.fn((customer, context) => Promise.resolve(context)),
            },
        });

        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                password: 'shopware',
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
                company: 'Shopware',
            },
        });

        expect(await wrapper.vm.languageId).toBe('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toBe('1');
    });

    it('should keep context when sales channel exists language compared to API language', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            languageRepositoryMock: {
                searchIds: () =>
                    Promise.resolve({
                        total: 1,
                        data: [Shopware.Context.api.languageId],
                    }),
            },
            customerRepositoryMock: {
                create: () => ({
                    id: '1',
                    addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                }),
                save: jest.fn((customer, context) => Promise.resolve(context)),
            },
        });

        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        await wrapper.setData({
            customer: {
                id: '1',
                email: 'user@domain.com',
                accountType: 'business',
                password: 'shopware',
                salesChannelId: 'a7921464677a4ef591683d144beecd24',
                company: 'Shopware',
            },
        });

        expect(await wrapper.vm.languageId).toEqual(Shopware.Context.api.languageId);

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toEqual(Shopware.Context.api.languageId);
    });

    it('should show error inside sw-tabs-item component', async () => {
        let swDetailsTab = wrapper.findAll('.sw-tabs-item').at(0);
        let swBillingAddressTab = wrapper.findAll('.sw-tabs-item').at(1);

        expect(swDetailsTab.find('.mt-icon').exists()).toBe(false);
        expect(swBillingAddressTab.find('.mt-icon').exists()).toBe(false);

        Shopware.Store.get('error').addApiError({
            expression: 'customer.1.email',
            error: new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        wrapper.vm.customerRepository.save = jest.fn(() => Promise.resolve());

        const saveButton = wrapper.findByText('button', 'global.default.save');

        await saveButton.trigger('click');

        swDetailsTab = wrapper.findAll('.sw-tabs-item').at(0);
        swBillingAddressTab = wrapper.findAll('.sw-tabs-item').at(1);

        expect(swDetailsTab.find('.mt-icon.icon--solid-exclamation-circle').exists()).toBe(true);
        expect(swBillingAddressTab.find('.mt-icon').exists()).toBe(false);
    });

    it('should get default salutation is value not specified', async () => {
        expect(wrapper.vm.customer.salutationId).toBe('salutationId');
    });

    it('should set defaultShippingAddressId to defaultBillingAddressId when newValue is true', async () => {
        await wrapper.setData({
            customer: {
                ...wrapper.vm.customer,
                defaultBillingAddressId: 'billing-address-id',
                isNew: jest.fn(() => false),
            },
        });

        wrapper.vm.isSameBilling = true;
        expect(wrapper.vm.customer.defaultShippingAddressId).toBe('billing-address-id');
    });

    it('should remove all addresses but default billing when customer is new and newValue is true', async () => {
        await wrapper.setData({
            customer: {
                ...wrapper.props().customer,
                defaultBillingAddressId: 'billing-address-id',
                shippingAddressId: 'shipping-address-id',
                addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
                    { id: 'billing-address-id' },
                    { id: 'shipping-address-id' },
                ]),
                isNew: jest.fn(() => true),
            },
        });

        wrapper.vm.isSameBilling = true;

        expect(wrapper.vm.customer.addresses.has('shipping-address-id')).toBe(false);
        expect(wrapper.vm.customer.addresses.has('billing-address-id')).toBe(true);
    });

    it('should create a new shipping address when newValue is false', async () => {
        await wrapper.setData({
            customer: {
                ...wrapper.props().customer,
                defaultBillingAddressId: 'billing-address-id',
                shippingAddressId: 'shipping-address-id',
                addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
                    { id: 'billing-address-id' },
                    { id: 'shipping-address-id' },
                ]),
                isNew: jest.fn(() => true),
            },
        });

        wrapper.vm.isSameBilling = true;
        wrapper.vm.isSameBilling = false;

        expect(wrapper.vm.customer.defaultShippingAddressId).toBe('new-shipping-address-id');
        expect(wrapper.vm.customer.addresses.has('new-shipping-address-id')).toBe(true);
        expect(wrapper.vm.defaultSalutationId).toBe('salutationId');
        expect(wrapper.vm.customer.addresses.get('new-shipping-address-id').salutationId).toBe('salutationId');
    });

    it('should does not change defaultShippingAddressId if newValue is the same as isSameBilling', async () => {
        await wrapper.setData({
            customer: {
                ...wrapper.vm.customer,
                defaultBillingAddressId: 'billing-address-id',
                defaultShippingAddressId: 'billing-address-id',
                isNew: jest.fn(() => false),
            },
        });

        const originalShippingAddressId = wrapper.vm.customer.defaultShippingAddressId;
        wrapper.vm.isSameBilling = true;

        expect(wrapper.vm.customer.defaultShippingAddressId).toBe(originalShippingAddressId);
    });
});
