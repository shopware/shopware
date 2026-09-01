import { mount } from '@vue/test-utils';
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

async function createWrapper(propsData, customerResponse = createCustomerMock()) {
    return mount(await wrapTestComponent('sw-order-address-selection', { sync: true }), {
        global: {
            directives: {
                popover: Shopware.Directive.getDirectiveRegistry().get('popover'),
            },
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-select-result': {
                    props: [
                        'item',
                        'index',
                    ],
                    template: `<li :class="componentClasses" class="sw-select-result" @click.stop="onClickResult">
                        <slot></slot></li>`,
                    methods: {
                        onClickResult() {
                            Shopware.Utils.EventBus.emit('item-select', this.item);
                        },
                    },
                    computed: {
                        componentClasses() {
                            return [
                                `sw-select-option--${this.index}`,
                            ];
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
                        get: () => Promise.resolve(customerResponse),
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
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be able to edit address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.findComponent('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectEdit = document.body.querySelector('.sw-select-option--0');

        selectEdit.querySelector('.sw-context-menu-item').click();

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

        const select = document.body.querySelector('.sw-select-option--1');

        select.click();
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

        const newAddress = document.body.querySelector('.sw-select-result-list__content ul:nth-of-type(1)');

        newAddress.querySelector('.sw-select-result__add-new-address').click();
        await flushPromises();

        expect(wrapper.vm.currentAddress._isNew).toBe(true);
        expect(wrapper.vm.currentAddress.customerId).toBe('63e27affb5804538b5b06cb4e344b130');
        expect(wrapper.find('.sw-customer-address-form')).toBeTruthy();
    });

    it('should not offer to create a new address when the customer was deleted', async () => {
        wrapper = await createWrapper({}, null);
        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.vm.customer).toBeNull();
        expect(document.body.querySelector('.sw-select-result__add-new-address')).toBeFalsy();
        expect(document.body.querySelectorAll('.sw-select-result')).toHaveLength(1);
    });

    it('should select a newly created address after saving it', async () => {
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
    });

    it('should keep id on options for addresses where id is not enumerable via spread', async () => {
        await flushPromises();

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

    it('should be able to get the options with props', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = document.body.querySelector('.sw-select-result-list__item-list');

        expect(list.querySelectorAll('.sw-select-result')).toHaveLength(2);

        const firstSelection = list.querySelector('.sw-select-result .sw-order-address-selection__information');
        expect(firstSelection.querySelectorAll('p').item(1).textContent.trim()).toBe('Muster SE - People & Culture');
        expect(firstSelection.querySelectorAll('p').item(2).textContent.trim()).toBe('Denesik Bridge');
        expect(firstSelection.querySelectorAll('p').item(3).textContent.trim()).toBe('05132 Bernierstad');
        expect(firstSelection.querySelectorAll('p').item(4).textContent.trim()).toBe('Buzbach');

        const secondSelection = list.querySelectorAll('.sw-select-result .sw-order-address-selection__information').item(1);
        expect(secondSelection.querySelectorAll('p').item(1).textContent.trim()).toBe('Stehr Divide');
        expect(secondSelection.querySelectorAll('p').item(2).textContent.trim()).toBe('64885-2245 Faheyshire');
        expect(secondSelection.querySelectorAll('p').item(3).textContent.trim()).toBe('Buzbach');
    });

    it('should be able to get the options with not props', async () => {
        wrapper = await createWrapper({
            address: null,
            addressId: null,
        });

        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = document.body.querySelector('.sw-select-result-list__item-list');

        const information = list.querySelector('.sw-select-result .sw-order-address-selection__information');

        expect(list.querySelectorAll('.sw-select-result')).toHaveLength(2);
        expect(information.querySelectorAll('p').item(1).textContent.trim()).toBe('Stehr Divide');
        expect(information.querySelectorAll('p').item(2).textContent.trim()).toBe('64885-2245 Faheyshire');
        expect(information.querySelectorAll('p').item(3).textContent.trim()).toBe('Buzbach');
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
