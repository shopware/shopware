import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const { ShopwareError } = Shopware.Classes;

async function createWrapper() {
    // The grid pages server side, so it keeps its own records. This address sits on a later page
    // and is therefore not part of the pre-loaded customer.addresses collection.
    const addressOnLaterPage = {
        id: 'address-on-later-page',
        customerId: '1',
        countryId: 'country-id',
        lastName: 'Mustermann',
        firstName: 'Max',
        city: 'Schoeppingen',
        street: 'Ebbinghoff 10',
        zipcode: '48624',
    };

    return mount(
        await wrapTestComponent('sw-customer-detail-addresses', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: { query: {} },
                },
                stubs: {
                    'mt-card': {
                        template: `<div class="mt-card">
                    <slot name="toolbar"></slot>
                    <slot name="grid"></slot>
                    <slot></slot>
                </div>`,
                    },
                    'sw-card-filter': {
                        template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
                    },
                    'sw-field': true,
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-one-to-many-grid': {
                        props: ['collection'],
                        data() {
                            return {
                                records: {
                                    get: (id) => {
                                        if (id === addressOnLaterPage.id) {
                                            return addressOnLaterPage;
                                        }

                                        return this.collection.find((address) => address.id === id);
                                    },
                                },
                            };
                        },
                        methods: {
                            load() {},
                        },
                        template: `
                    <table>
                        <tbody>
                            <td v-for="item in collection">
                                <slot name="column-lastName" v-bind="{ item }"></slot>
                                <slot name="actions" v-bind="{ item }"></slot>
                            </td>
                        </tbody>
                    </table>
                `,
                    },
                    'sw-context-menu-item': {
                        emits: ['click'],
                        template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>',
                    },
                    'sw-customer-address-form': {
                        name: 'sw-customer-address-form',
                        props: ['disabled'],
                        template: '<div class="sw-customer-address-form"><slot></slot></div>',
                    },
                    'sw-customer-address-form-options': {
                        name: 'sw-customer-address-form-options',
                        props: ['disabled'],
                        template: '<div class="sw-customer-address-form-options"></div>',
                    },
                    'sw-radio-field': true,
                    'sw-address': true,
                },

                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve([]),
                                create: () => ({ id: '' }),
                                clone: jest.fn(() =>
                                    Promise.resolve({
                                        id: 'clone-address-id',
                                    }),
                                ),
                                save: jest.fn(() => Promise.resolve()),
                                get: (id) => {
                                    if (id === 'clone-address-id') {
                                        return Promise.resolve({
                                            id: 'clone-address-id',
                                            lastName: 'Thu',
                                            firstName: 'Vo',
                                            city: 'Berlin',
                                            street: 'Legiendamm',
                                            zipcode: '550000',
                                        });
                                    }

                                    return Promise.reject();
                                },
                            };
                        },
                    },
                },
            },

            props: {
                customerEditMode: false,
                customer: {
                    id: '1',
                    addresses: [
                        {
                            id: '1',
                            lastName: 'Nguyen',
                            firstName: 'Quynh',
                            city: 'Berlin',
                            street: 'Legiendamm',
                            zipcode: '550000',
                        },
                    ],
                },
            },
        },
    );
}

describe('module/sw-customer/view/sw-customer-detail-addresses.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should show text on last name column  when edit mode is off', async () => {
        const lastNameCell = wrapper.find('td');

        expect(lastNameCell.find('a').exists()).toBeFalsy();
        expect(lastNameCell.text()).toContain('Nguyen');
    });

    it('should show link on last name column when edit mode is on', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });

        const lastNameCell = wrapper.find('td');

        expect(lastNameCell.find('a').exists()).toBeTruthy();
        expect(lastNameCell.find('a').text()).toContain('Nguyen');
    });

    it('should set not_specified salutation key when creating a new address', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });
        wrapper.vm.salutationRepository.searchIds = jest.fn(() => Promise.resolve({ data: ['1'] }));

        expect(wrapper.vm.currentAddress).toBeNull();

        const swButton = wrapper.findByText('button', 'sw-customer.detailAddresses.buttonAddAddress');
        await swButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.currentAddress.salutationId).toBe('1');
    });

    it('should dispatch error/addApiError when the form has invalid field errors', async () => {
        const entityMock = {
            getEntityName: () => 'customer_address',
            id: '1',
        };

        await wrapper.setData({
            currentAddress: {
                id: '1',
                lastName: 'Wiegand',
                firstName: 'Daisha',
                city: 'Lake Waldo',
                customerId: '1',
            },
        });

        expect(Shopware.Store.get('error').getApiError(entityMock, 'street')).toBeNull();

        await wrapper.vm.onSaveAddress();

        expect(Shopware.Store.get('error').getApiError(entityMock, 'street')).toBeInstanceOf(ShopwareError);
    });

    it('should clear the error of a required field once it holds a value again', async () => {
        const entityMock = {
            getEntityName: () => 'customer_address',
            id: '1',
        };

        await wrapper.setData({
            currentAddress: {
                id: '1',
                lastName: 'Wiegand',
                firstName: 'Daisha',
                city: 'Lake Waldo',
                customerId: '1',
            },
        });

        await wrapper.vm.onSaveAddress();

        expect(Shopware.Store.get('error').getApiError(entityMock, 'street')).toBeInstanceOf(ShopwareError);

        wrapper.vm.currentAddress.street = 'Stehr Divide';

        await wrapper.vm.onSaveAddress();

        expect(Shopware.Store.get('error').getApiError(entityMock, 'street')).toBeNull();
    });

    it('should not leave any warnings on the customer page when closing the address modal', async () => {
        const errorStore = Shopware.Store.get('error');

        await wrapper.setData({
            currentAddress: {
                id: '1',
                lastName: 'Wiegand',
                firstName: 'Daisha',
                city: 'Lake Waldo',
                customerId: '1',
            },
        });

        await wrapper.vm.onSaveAddress();

        expect(errorStore.getErrorsForEntity('customer_address', '1')).not.toBeNull();

        wrapper.vm.onCloseAddressModal();
        await flushPromises();

        expect(errorStore.getErrorsForEntity('customer_address', '1')).toBeNull();
        expect(wrapper.vm.currentAddress).toBeNull();
    });

    it('should keep a server reported error when closing the address modal', async () => {
        const errorStore = Shopware.Store.get('error');
        const entityMock = {
            getEntityName: () => 'customer_address',
            id: '2',
        };

        errorStore.addApiError({
            expression: 'customer_address.2.additionalAddressLine1',
            error: new ShopwareError({ code: 'ADDITIONAL_ADDR1_IS_TOO_LONG' }),
        });

        await wrapper.setData({ currentAddress: { id: '2' } });

        wrapper.vm.onCloseAddressModal();
        await flushPromises();

        expect(errorStore.getApiError(entityMock, 'additionalAddressLine1')).toBeInstanceOf(ShopwareError);
    });

    it('should clear pending required field errors when the view is torn down', async () => {
        const errorStore = Shopware.Store.get('error');
        const entityMock = {
            getEntityName: () => 'customer_address',
            id: '3',
        };

        await wrapper.setData({
            currentAddress: {
                id: '3',
                lastName: 'Wiegand',
                firstName: 'Daisha',
                city: 'Lake Waldo',
                customerId: '1',
            },
        });

        await wrapper.vm.onSaveAddress();

        expect(errorStore.getApiError(entityMock, 'street')).toBeInstanceOf(ShopwareError);

        wrapper.unmount();

        expect(errorStore.getApiError(entityMock, 'street')).toBeNull();
    });

    it('should clone address line correctly', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });

        let lines = wrapper.findAll('td');
        expect(lines).toHaveLength(1);
        expect(wrapper.vm.$data.activeCustomer.addresses).toHaveLength(1);

        const contextMenus = wrapper.findAll('.sw-context-menu-item');

        expect(contextMenus).toHaveLength(5);
        expect(contextMenus.at(1).text()).toBe('global.default.duplicate');

        await contextMenus.at(1).trigger('click');
        await flushPromises();

        lines = wrapper.findAll('td');
        expect(lines).toHaveLength(2);
        expect(wrapper.vm.$data.activeCustomer.addresses).toHaveLength(2);

        expect(lines.at(1).find('a').exists()).toBeTruthy();
        expect(lines.at(1).text()).toContain('Thu');
    });

    it('should edit an address that the grid loaded on a later page', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });

        expect(
            wrapper.vm.activeCustomer.addresses.find((address) => address.id === 'address-on-later-page'),
        ).toBeUndefined();

        wrapper.vm.onEditAddress('address-on-later-page');

        expect(wrapper.vm.currentAddress).toEqual(
            expect.objectContaining({
                id: 'address-on-later-page',
                firstName: 'Max',
                lastName: 'Mustermann',
                street: 'Ebbinghoff 10',
            }),
        );
    });

    it('should save an address that the grid loaded on a later page', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });

        wrapper.vm.onEditAddress('address-on-later-page');
        wrapper.vm.currentAddress.city = 'Berlin';

        wrapper.vm.onSaveAddress();
        await flushPromises();

        expect(wrapper.vm.customerAddressRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'address-on-later-page',
                city: 'Berlin',
            }),
        );
        expect(wrapper.vm.currentAddress).toBeNull();
    });

    it('should disable address form options when edit mode is off', async () => {
        await wrapper.setData({
            currentAddress: {
                id: '1',
            },
        });

        expect(wrapper.getComponent('.sw-customer-address-form').props('disabled')).toBe(true);
        expect(wrapper.getComponent('.sw-customer-address-form-options').props('disabled')).toBe(true);
    });

    it('should enable address form options when edit mode is on', async () => {
        await wrapper.setProps({
            customerEditMode: true,
        });
        await wrapper.setData({
            currentAddress: {
                id: '1',
            },
        });

        expect(wrapper.getComponent('.sw-customer-address-form').props('disabled')).toBe(false);
        expect(wrapper.getComponent('.sw-customer-address-form-options').props('disabled')).toBe(false);
    });
});
