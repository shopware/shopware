import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderNewCustomerModal from 'src/module/sw-order/component/sw-order-new-customer-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

/**
 * @package customer-order
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-order-new-customer-modal', swOrderNewCustomerModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-new-customer-modal'), {
        localVue,
        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-customer-address-form': true,
            'sw-customer-base-form': true,
            'sw-icon': true,
            'sw-field': true,
        },
        provide: {
            repositoryFactory: {
                create: (entity) => {
                    if (entity === 'customer') {
                        return {
                            create: () => {
                                return {
                                    id: '1',
                                    addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                                };
                            }
                        };
                    }

                    if (entity === 'language') {
                        return {
                            searchIds: () => Promise.resolve({
                                total: 1,
                                data: ['1'],
                            })
                        };
                    }

                    return {
                        create: () => Promise.resolve()
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
                }
            },
            customerValidationService: {
                checkCustomerEmail: () => Promise.resolve(),
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-new-customer-modal', () => {
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

        expect(await wrapper.vm.languageId).toEqual('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toEqual('1');
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
});
