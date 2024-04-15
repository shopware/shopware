import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

// eslint-disable-next-line import/named
import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package checkout
 */

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
                        id: '3a2e625b-f5e1-46d8-9e76-68c0e9b672a1',
                    },
                },
            ],
        },
    });

    return mount(await wrapTestComponent('sw-customer-address-form', { sync: true }), {
        props: {
            customer: {},
            address: {
                _isNew: true,
                id: '1',
                getEntityName: () => { return 'customer_address'; },
            },
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-entity-single-select': true,
                'sw-icon': true,
            },
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'country') {
                            return {
                                get: (id) => {
                                    if (id) {
                                        return Promise.resolve({
                                            id,
                                            name: 'Germany',
                                        });
                                    }

                                    return Promise.resolve({});
                                },
                            };
                        }

                        return {
                            search: (criteria = {}) => {
                                const countryIdFilter = criteria?.filters.find(item => item.field === 'countryId');

                                if (countryIdFilter?.value === '1') {
                                    return Promise.resolve([{
                                        id: 'state1',
                                    }]);
                                }
                                return Promise.resolve([]);
                            },
                        };
                    },
                },
            },
        },
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
                countryId: '2',
                getEntityName: () => { return 'customer_address'; },
            },
        });

        await flushPromises();

        const stateSelect = wrapper.find('.sw-customer-address-form__state-select');
        expect(stateSelect.exists()).toBeFalsy();
    });

    it('should show state field if country has states', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            address: {
                countryId: '1',
                getEntityName: () => { return 'customer_address'; },
            },
        });

        await flushPromises();

        const stateSelect = wrapper.find('.sw-customer-address-form__state-select');
        expect(stateSelect.exists()).toBeTruthy();
    });

    it('should mark company as required field when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                accountType: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
            },
            address: {},
        });

        await flushPromises();

        expect(wrapper.find('input[label="sw-customer.addressForm.labelCompany"]')
            .attributes('required')).toBeDefined();
    });

    it('should not mark company as required when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                accountType: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
            },
        });

        await flushPromises();

        expect(wrapper.find('[label="sw-customer.addressForm.labelCompany"]')
            .attributes('required')).toBeUndefined();
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

    it('should hide the error field when a disabled field', async () => {
        await Shopware.State.dispatch('error/addApiError', {
            expression: 'customer_address.1.firstName',
            error: new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
                selfLink: 'customer_address.1.firstName',
            }),
        });

        const wrapper = await createWrapper();

        await flushPromises();

        const firstName = wrapper.findAll('.sw-field').at(3);

        expect(wrapper.vm.disabled).toBe(false);
        expect(firstName.classes()).toContain('has--error');
        expect(firstName.find('.sw-field__error').text()).toBe('This value should not be blank.');

        await wrapper.setProps({ disabled: true });
        await flushPromises();

        expect(wrapper.vm.disabled).toBe(true);
        expect(firstName.classes()).not.toContain('has--error');
        expect(firstName.find('.sw-field__error').exists()).toBeFalsy();
    });

    it('should set required attribute based on the configuration of the country', async () => {
        const wrapper = await createWrapper();

        const definition = Shopware.EntityDefinition.get('customer_address');

        expect(definition.properties.zipcode.flags?.required).toBeUndefined();
        expect(definition.properties.countryStateId.flags?.required).toBeUndefined();

        await wrapper.setData({
            country: {
                postalCodeRequired: true,
                forceStateInRegistration: true,
            },
        });

        await flushPromises();

        expect(definition.properties.zipcode.flags?.required).toBe(true);
        expect(definition.properties.countryStateId.flags?.required).toBe(true);
    });

    it('should dispatch error/removeApiError based on the configuration of the country', async () => {
        // add mock for dispatch
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        const wrapper = await createWrapper();

        await wrapper.setData({
            country: {
                postalCodeRequired: false,
                forceStateInRegistration: false,
            },
        });

        const address = wrapper.vm.address;

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/removeApiError', {
            expression: `${address.getEntityName()}.${address.id}.zipcode`,
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/removeApiError', {
            expression: `${address.getEntityName()}.${address.id}.countryStateId`,
        });
    });
});
