import { shallowMount } from '@vue/test-utils';
import swCustomerAddressForm from 'src/module/sw-customer/component/sw-customer-address-form';

// eslint-disable-next-line import/named
import CUSTOMER from '../../constant/sw-customer.constant';
/**
 * @package customer-order
 */

Shopware.Component.register('sw-customer-address-form', swCustomerAddressForm);

async function createWrapper() {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/country',
        status: 200,
        response: {
            data: [
                {
                    id: 'bc05040b-9da1-41ec-93ad-add9d33cd731',
                    attributes: {
                        id: '3a2e625b-f5e1-46d8-9e76-68c0e9b672a1'
                    }
                }
            ]
        }
    });

    return shallowMount(await Shopware.Component.build('sw-customer-address-form'), {
        propsData: {
            customer: {},
            address: {}
        },
        stubs: {
            'sw-container': true,
            'sw-text-field': true,
            'sw-entity-single-select': true
        },
        provide: {
            repositoryFactory: {
                create: (entity) => {
                    if (entity === 'country') {
                        return {
                            get: (id) => {
                                if (id) {
                                    return Promise.resolve({
                                        id,
                                        name: 'Germany'
                                    });
                                }

                                return Promise.resolve({});
                            }
                        };
                    }

                    return {
                        search: (criteria = {}) => {
                            const countryIdFilter = criteria?.filters.find(item => item.field === 'countryId');

                            if (countryIdFilter?.value === '1') {
                                return Promise.resolve([{
                                    id: 'state1'
                                }]);
                            }
                            return Promise.resolve([]);
                        }
                    };
                },
            },
        }
    });
}

describe('module/sw-customer/page/sw-customer-address-form', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = { type: 'not', operator: 'or', queries: [{ field: 'id', type: 'equals', value: 'ed643807c9f84cc8b50132ea3ccb1c3b' }] };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should hide state field if country dont have states', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            address: {
                countryId: '2'
            }
        });

        await wrapper.vm.$nextTick();

        const stateSelect = wrapper.find('.sw-customer-address-form__state-select');
        expect(stateSelect.exists()).toBeFalsy();
    });

    it('should show state field if country has states', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            address: {
                countryId: '1'
            }
        });

        await wrapper.vm.$nextTick();

        const stateSelect = wrapper.find('.sw-customer-address-form__state-select');
        expect(stateSelect.exists()).toBeTruthy();
    });

    it('should mark company as required field when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                accountType: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
            },
            address: {}
        });

        expect(wrapper.find('[label="sw-customer.addressForm.labelCompany"]')
            .attributes('required')).toBeTruthy();
    });

    it('should not mark company as required when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                accountType: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
            }
        });

        expect(wrapper.find('[label="sw-customer.addressForm.labelCompany"]')
            .attributes('required')).toBeFalsy();
    });

    it('should display company, department and vat fields by default when account type is empty', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                company: 'shopware',
            },
            address: {},
        });

        expect(wrapper.find('[label="sw-customer.addressForm.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[label="sw-customer.addressForm.labelDepartment"]').exists()).toBeTruthy();
    });
});
