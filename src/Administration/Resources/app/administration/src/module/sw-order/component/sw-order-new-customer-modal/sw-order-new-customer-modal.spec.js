import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-new-customer-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

/**
 * @package customer-order
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

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

    it('should show email error validation', async () => {
        console.log(wrapper.html());
    });
});
