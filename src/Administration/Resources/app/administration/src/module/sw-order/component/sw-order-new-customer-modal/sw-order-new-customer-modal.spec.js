import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

/**
 * @package customer-order
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;


async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-new-customer-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-icon': true,
                'sw-switch-field': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'customer') {
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
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
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
                company: 'Shopware',
            },
        });

        expect(await wrapper.vm.languageId).toBe('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toBe('1');
    });

    it('should keep context when sales channel exists language compared to API language', async () => {
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

        expect(swDetailsTab.find('sw-icon-stub').exists()).toBe(false);
        expect(swBillingAddressTab.find('sw-icon-stub').exists()).toBe(false);

        await Shopware.State.dispatch('error/addApiError', {
            expression: 'customer.1.email',
            error: new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        wrapper.vm.customerRepository.save = jest.fn(() => Promise.resolve());

        const saveButton = wrapper.find('.sw-button--primary');

        await saveButton.trigger('click');

        swDetailsTab = wrapper.findAll('.sw-tabs-item').at(0);
        swBillingAddressTab = wrapper.findAll('.sw-tabs-item').at(1);

        expect(swDetailsTab.find('sw-icon-stub[name=solid-exclamation-circle]').exists()).toBe(true);
        expect(swBillingAddressTab.find('sw-icon-stub').exists()).toBe(false);
    });

    it('should get default salutation is value not specified', async () => {
        expect(wrapper.vm.customer.salutationId).toBe('salutationId');
    });
});
