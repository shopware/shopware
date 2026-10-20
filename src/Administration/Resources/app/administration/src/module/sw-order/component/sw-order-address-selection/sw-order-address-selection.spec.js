import { DOMWrapper, mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';
import EntityValidationService from 'src/app/service/entity-validation.service';

/**
 * @sw-package checkout
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

function createCustomerMock() {
    return {
        id: '63e27affb5804538b5b06cb4e344b130',
        addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
            {
                street: 'Stehr Divide',
                zipcode: '64885-2245',
                city: 'Faheyshire',
                id: '652e9e571cc94bd898077f256dcf629f',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                hash: 'isUnique',
                getEntityName: () => 'customer_address',
            },
            {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: '652e9e571cc94bd898077f256dcf6233',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                countryState: {
                    translated: {
                        name: 'NRW',
                    },
                },
                hash: 'isDuplicate',
                getEntityName: () => 'customer_address',
            },
        ]),
    };
}

async function createWrapper(propsData, customerResponse = createCustomerMock(), { customerGet } = {}) {
    const getCustomer = customerGet ?? jest.fn(() => Promise.resolve(customerResponse));

    const wrapper = mount(await wrapTestComponent('sw-order-address-selection', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `<li :class="componentClasses" class="sw-select-result" @click.stop="onClickResult">
                        <slot></slot></li>`,
                    methods: {
                        onClickResult() {
                            Shopware.Utils.EventBus.emit('item-select', this.item);
                        },
                    },
                    computed: {
                        componentClasses() {
                            return [`sw-select-option--${this.index}`];
                        },
                    },
                },
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-customer-address-form': await wrapTestComponent('sw-customer-address-form'),
                'sw-address': {
                    props: ['formattingAddress'],
                    template: '<div class="sw-address">{{ formattingAddress }}</div>',
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-customer-address-form-options': await wrapTestComponent('sw-customer-address-form-options'),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'router-link': true,
                'sw-product-variant-info': true,
                'sw-field-copyable': true,
                'sw-contextual-field': true,
                'sw-checkbox-field': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([]);
                        },
                        save: () => {
                            return Promise.resolve();
                        },
                        get: getCustomer,
                        create: () => ({
                            _isNew: true,
                            getEntityName: () => 'customer_address',
                        }),
                    }),
                },
                customSnippetApiService: {
                    render: (address) => {
                        return Promise.resolve({
                            rendered: `${address.street}, ${address.zipcode} ${address.city}`,
                        });
                    },
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
        props: {
            address: {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: '38e8895864a649a1b2ec806dad02ab87',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                countryState: {
                    translated: {
                        name: 'NRW',
                    },
                },
                hash: 'isDuplicate',
                getEntityName: () => 'order_address',
            },
            addressId: '38e8895864a649a1b2ec806dad02ab87',
            type: 'billing',
            ...propsData,
        },
    });

    wrapper.vm.customerGetMock = getCustomer;

    return wrapper;
}

describe('src/module/sw-order/component/sw-order-address-selection', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.unregister('swOrderDetail');
        Shopware.Store.register({
            id: 'swOrderDetail',
            state: () => ({
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
                customer: null,
                order: {
                    addresses: [
                        {
                            street: 'Denesik Bridge',
                            zipcode: '05132',
                            city: 'Bernierstad',
                            id: '38e8895864a649a1b2ec806dad02ab87',
                            country: {
                                translated: {
                                    name: 'Buzbach',
                                },
                            },
                        },
                    ],
                    billingAddressId: '38e8895864a649a1b2ec806dad02ab87',
                    orderCustomer: {
                        customerId: '63e27affb5804538b5b06cb4e344b130',
                    },
                },
            }),
            actions: {
                setCustomer(customer) {
                    this.customer = customer;
                },
            },
        });
    });

    beforeEach(async () => {
        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should be able to edit address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.findComponent('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectEdit = new DOMWrapper(document.body).get('.sw-select-option--0');

        await selectEdit.get('.sw-context-menu-item').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.currentAddress).toMatchObject({
            street: 'Denesik Bridge',
            zipcode: '05132',
            city: 'Bernierstad',
            company: 'Muster SE',
            id: '38e8895864a649a1b2ec806dad02ab87',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            countryState: {
                translated: {
                    name: 'NRW',
                },
            },
            department: 'People & Culture',
            hash: 'isDuplicate',
        });
    });

    it('should be able to change the address', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        expect(addressSelection.find('.sw-single-select__selection-text').text()).toBe(
            'Muster SE - People & Culture, Denesik Bridge, 05132 Bernierstad, NRW, Buzbach',
        );

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const select = new DOMWrapper(document.body).get('.sw-select-option--1');

        await select.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('change-address')).toBeTruthy();
        expect(wrapper.emitted('change-address')[0]).toEqual([
            {
                orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                customerAddressId: '652e9e571cc94bd898077f256dcf629f',
                type: 'billing',
                edited: false,
            },
        ]);
    });

    it('should be able to create new address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const newAddress = new DOMWrapper(document.body).get('.sw-select-result-list__content ul:nth-of-type(1)');

        await newAddress.get('.sw-select-result__add-new-address').trigger('click');
        await flushPromises();

        expect(wrapper.vm.currentAddress._isNew).toBe(true);
        expect(wrapper.vm.currentAddress.customerId).toBe('63e27affb5804538b5b06cb4e344b130');
        expect(wrapper.find('.sw-customer-address-form')).toBeTruthy();
    });

    it('should not offer to create a new address when the customer was deleted', async () => {
        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({}, null);
        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.vm.customer).toBeNull();
        expect(new DOMWrapper(document.body).find('.sw-select-result__add-new-address').exists()).toBeFalsy();
        expect(new DOMWrapper(document.body).findAll('.sw-select-result')).toHaveLength(1);
    });

    it('should select a newly created address after saving it', async () => {
        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({
            type: 'shipping',
        });
        await flushPromises();

        wrapper.vm.createNewCustomerAddress();
        Object.assign(wrapper.vm.currentAddress, {
            id: 'newCustomerAddressId',
            firstName: 'Ada',
            lastName: 'Lovelace',
            street: 'Example Street 1',
            zipcode: '12345',
            city: 'Example City',
            countryId: 'countryId',
        });

        wrapper.vm.isValidAddress = jest.fn(() => true);

        await wrapper.vm.onSaveAddress();
        await flushPromises();

        expect(wrapper.emitted('change-address')).toEqual([
            [
                {
                    orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                    customerAddressId: 'newCustomerAddressId',
                    type: 'shipping',
                    edited: false,
                },
            ],
        ]);
        expect(wrapper.vm.customerGetMock).toHaveBeenCalledTimes(2);
    });

    it('should keep id on options for addresses where id is not enumerable via spread', async () => {
        const newAddressId = 'new-customer-address-without-enumerable-id';
        const draft = {
            street: 'Ada Street 1',
            zipcode: '12345',
            city: 'Example City',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            hash: 'brandNewAddress',
            getEntityName: () => 'customer_address',
        };
        // Mimic Entity proxy: id is readable but missing from Object spread / own keys
        const entityLikeAddress = new Proxy(draft, {
            get(target, property) {
                if (property === 'id') {
                    return newAddressId;
                }

                return target[property];
            },
        });

        wrapper.vm.customer.addresses.push(entityLikeAddress);

        const option = wrapper.vm.addressOptions.find((item) => item.street === 'Ada Street 1');

        expect(option).toBeDefined();
        expect(option.id).toBe(newAddressId);
        // Selecting uses option.id as customerAddressId; without the explicit assignment this is undefined
        expect({ ...draft }.id).toBeUndefined();

        wrapper.vm.onAddressChange(option.id);

        expect(wrapper.emitted('change-address').at(-1)).toEqual([
            {
                orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                customerAddressId: newAddressId,
                type: 'billing',
                edited: false,
            },
        ]);
    });

    it('should share reloaded customer addresses with sibling selectors', async () => {
        const reloadedCustomer = createCustomerMock();
        reloadedCustomer.addresses.push({
            street: 'Shared Street',
            zipcode: '99999',
            city: 'Shared City',
            id: 'shared-new-address-id',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            hash: 'sharedNew',
            getEntityName: () => 'customer_address',
        });

        const customerGet = jest.fn().mockResolvedValueOnce(createCustomerMock()).mockResolvedValueOnce(reloadedCustomer);

        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({ type: 'billing' }, createCustomerMock(), { customerGet });
        await flushPromises();

        const shippingWrapper = await createWrapper({ type: 'shipping' }, createCustomerMock(), {
            customerGet: jest.fn(() => Promise.resolve(createCustomerMock())),
        });
        await flushPromises();

        // Second selector skips fetch when store already has the same customer
        expect(shippingWrapper.vm.customerGetMock).not.toHaveBeenCalled();
        expect(shippingWrapper.vm.customer).toBe(wrapper.vm.customer);

        wrapper.vm.createNewCustomerAddress();
        Object.assign(wrapper.vm.currentAddress, {
            id: 'shared-new-address-id',
            street: 'Shared Street',
            zipcode: '99999',
            city: 'Shared City',
            countryId: 'countryId',
        });
        wrapper.vm.isValidAddress = jest.fn(() => true);

        await wrapper.vm.onSaveAddress();
        await flushPromises();

        expect(customerGet).toHaveBeenCalledTimes(2);
        expect(shippingWrapper.vm.addressOptions.some((option) => option.id === 'shared-new-address-id')).toBe(true);

        shippingWrapper.unmount();
    });

    it('should dedupe reloaded customer address that matches the order address hash', async () => {
        const reloadedCustomer = createCustomerMock();
        // Same content/hash as the current order address → must not appear twice in options
        reloadedCustomer.addresses.push({
            street: 'Denesik Bridge',
            zipcode: '05132',
            city: 'Bernierstad',
            company: 'Muster SE',
            department: 'People & Culture',
            id: 'new-but-same-hash-address',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            countryState: {
                translated: {
                    name: 'NRW',
                },
            },
            hash: 'isDuplicate',
            getEntityName: () => 'customer_address',
        });

        const customerGet = jest.fn().mockResolvedValueOnce(createCustomerMock()).mockResolvedValueOnce(reloadedCustomer);

        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({}, createCustomerMock(), { customerGet });
        await flushPromises();

        wrapper.vm.createNewCustomerAddress();
        Object.assign(wrapper.vm.currentAddress, {
            id: 'new-but-same-hash-address',
            street: 'Denesik Bridge',
            zipcode: '05132',
            city: 'Bernierstad',
            company: 'Muster SE',
            department: 'People & Culture',
            countryId: 'countryId',
        });
        wrapper.vm.isValidAddress = jest.fn(() => true);

        await wrapper.vm.onSaveAddress();
        await flushPromises();

        const optionsWithSameStreet = wrapper.vm.addressOptions.filter((option) => option.street === 'Denesik Bridge');

        expect(optionsWithSameStreet).toHaveLength(1);
        expect(optionsWithSameStreet[0].id).toBe('38e8895864a649a1b2ec806dad02ab87');
        expect(wrapper.vm.addressOptions.some((option) => option.id === 'new-but-same-hash-address')).toBe(false);
    });

    it('should keep both options when hashes differ', async () => {
        const selectedCustomerAddressId = 'different-company-address';

        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({
            addressId: selectedCustomerAddressId,
            address: {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: '38e8895864a649a1b2ec806dad02ab87',
                hash: 'order-hash-a',
                firstName: 'Max',
                lastName: 'Mustermann',
                countryId: 'country-order',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                getEntityName: () => 'order_address',
            },
        });
        await flushPromises();

        wrapper.vm.customer.addresses = new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
            {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: selectedCustomerAddressId,
                hash: 'customer-hash-b',
                firstName: 'Max',
                lastName: 'Mustermann',
                countryId: 'country-customer',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                getEntityName: () => 'customer_address',
            },
        ]);

        const optionIds = wrapper.vm.addressOptions.map((option) => option.id);

        expect(optionIds).toContain(selectedCustomerAddressId);
        expect(optionIds).toContain('38e8895864a649a1b2ec806dad02ab87');
    });

    it('should keep both options when only company differs', async () => {
        const selectedCustomerAddressId = 'no-company-customer-address';

        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({
            addressId: selectedCustomerAddressId,
            address: {
                firstName: 'Max',
                lastName: 'Mustermann',
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Active Company GmbH',
                id: '38e8895864a649a1b2ec806dad02ab87',
                hash: 'order-with-company',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                getEntityName: () => 'order_address',
            },
        });
        await flushPromises();

        wrapper.vm.customer.addresses = new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
            {
                firstName: 'Max',
                lastName: 'Mustermann',
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: null,
                id: selectedCustomerAddressId,
                hash: 'customer-without-company',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                getEntityName: () => 'customer_address',
            },
        ]);

        const optionIds = wrapper.vm.addressOptions.map((option) => option.id);

        expect(optionIds).toContain(selectedCustomerAddressId);
        expect(optionIds).toContain('38e8895864a649a1b2ec806dad02ab87');
    });

    it('should be able to get the options with props', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = new DOMWrapper(document.body).get('.sw-select-result-list__item-list');

        expect(list.findAll('.sw-select-result')).toHaveLength(2);

        const firstSelection = list.get('.sw-select-result .sw-order-address-selection__information');
        expect(firstSelection.findAll('p').at(1).text()).toBe('Muster SE - People & Culture');
        expect(firstSelection.findAll('p').at(2).text()).toBe('Denesik Bridge');
        expect(firstSelection.findAll('p').at(3).text()).toBe('05132 Bernierstad');
        expect(firstSelection.findAll('p').at(4).text()).toBe('Buzbach');

        const secondSelection = list.findAll('.sw-select-result .sw-order-address-selection__information').at(1);
        expect(secondSelection.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(secondSelection.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
        expect(secondSelection.findAll('p').at(3).text()).toBe('Buzbach');
    });

    it('should be able to get the options with not props', async () => {
        Shopware.Store.get('swOrderDetail').setCustomer(null);
        wrapper = await createWrapper({
            address: null,
            addressId: null,
        });

        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = new DOMWrapper(document.body).get('.sw-select-result-list__item-list');

        const information = list.get('.sw-select-result .sw-order-address-selection__information');

        expect(list.findAll('.sw-select-result')).toHaveLength(2);
        expect(information.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(information.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
        expect(information.findAll('p').at(3).text()).toBe('Buzbach');
    });

    it('should report and clear required field errors when validating an address', async () => {
        await flushPromises();

        const errorStore = Shopware.Store.get('error');
        jest.spyOn(Shopware.EntityDefinition, 'getRequiredFields').mockReturnValue({
            firstName: {},
            lastName: {},
        });

        const address = {
            id: 'new-address-id',
            firstName: '',
            lastName: 'Lovelace',
            getEntityName: () => 'customer_address',
        };

        errorStore.addApiError({
            expression: 'customer_address.new-address-id.lastName',
            error: new ShopwareError({ code: EntityValidationService.ERROR_CODE_REQUIRED }),
        });

        expect(wrapper.vm.isValidAddress(address)).toBe(false);

        expect(errorStore.getApiError(address, 'firstName')).toBeInstanceOf(ShopwareError);
        expect(errorStore.getApiError(address, 'lastName')).toBeNull();
    });

    it('should keep a server reported error when validating an address', async () => {
        await flushPromises();

        const errorStore = Shopware.Store.get('error');
        jest.spyOn(Shopware.EntityDefinition, 'getRequiredFields').mockReturnValue({
            firstName: {},
            lastName: {},
        });

        const address = {
            id: 'new-address-id',
            firstName: '',
            lastName: 'Lovelace',
            getEntityName: () => 'customer_address',
        };

        errorStore.addApiError({
            expression: 'customer_address.new-address-id.lastName',
            error: new ShopwareError({ code: 'LAST_NAME_IS_TOO_LONG' }),
        });

        expect(wrapper.vm.isValidAddress(address)).toBe(false);

        expect(errorStore.getApiError(address, 'lastName')).toBeInstanceOf(ShopwareError);
    });

    it('should not leave any warnings on the order page when the address modal is closed', async () => {
        await flushPromises();

        const errorStore = Shopware.Store.get('error');

        wrapper.vm.currentAddress = {
            id: 'closed-address-id',
            getEntityName: () => 'customer_address',
        };

        await expect(wrapper.vm.onSaveAddress()).rejects.toBeUndefined();

        expect(errorStore.getErrorsForEntity('customer_address', 'closed-address-id')).not.toBeNull();

        wrapper.vm.currentAddress = null;
        await flushPromises();

        expect(errorStore.getErrorsForEntity('customer_address', 'closed-address-id')).toBeNull();
    });

    it('should keep a server reported error when the address modal is closed', async () => {
        await flushPromises();

        const errorStore = Shopware.Store.get('error');
        const address = { id: 'kept-address-id', getEntityName: () => 'customer_address' };

        errorStore.addApiError({
            expression: 'customer_address.kept-address-id.additionalAddressLine1',
            error: new ShopwareError({ code: 'ADDITIONAL_ADDR1_IS_TOO_LONG' }),
        });

        wrapper.vm.currentAddress = address;
        await flushPromises();

        wrapper.vm.currentAddress = null;
        await flushPromises();

        expect(errorStore.getApiError(address, 'additionalAddressLine1')).toBeInstanceOf(ShopwareError);
    });

    it('should clear pending required field errors when the component is torn down', async () => {
        await flushPromises();

        const errorStore = Shopware.Store.get('error');
        const address = { id: 'torn-down-address-id', getEntityName: () => 'customer_address' };

        errorStore.addApiError({
            expression: 'customer_address.torn-down-address-id.firstName',
            error: new ShopwareError({ code: EntityValidationService.ERROR_CODE_REQUIRED }),
        });

        wrapper.vm.currentAddress = address;
        await flushPromises();

        wrapper.unmount();

        expect(errorStore.getApiError(address, 'firstName')).toBeNull();
    });

    it('should show a notification when trying to save an invalid address', async () => {
        await flushPromises();

        const notificationSpy = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.currentAddress = {
            id: 'new-address-id',
            firstName: '',
            getEntityName: () => 'customer_address',
        };

        await expect(wrapper.vm.onSaveAddress()).rejects.toBeUndefined();
        expect(notificationSpy).toHaveBeenCalled();
    });

    it('renders the selected address details below the select', async () => {
        await flushPromises();

        const selectedAddress = wrapper.find('.sw-order-address-selection__selected-address');
        const selectedAddressContent = selectedAddress.find('.sw-address');

        expect(selectedAddress.exists()).toBe(true);
        expect(selectedAddressContent.text()).toBe('Denesik Bridge, 05132 Bernierstad');
    });
});
