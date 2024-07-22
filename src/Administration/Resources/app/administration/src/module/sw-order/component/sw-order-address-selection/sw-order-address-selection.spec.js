import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderAddressSelection from 'src/module/sw-order/component/sw-order-address-selection';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import swCustomerAddressForm from 'src/module/sw-customer/component/sw-customer-address-form';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/form/field-base/sw-base-field';

/**
 * @package checkout
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-order-address-selection', swOrderAddressSelection);
Shopware.Component.register('sw-customer-address-form', swCustomerAddressForm);

async function createWrapper(propsData) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-address-selection'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-block-field': true,
            'sw-icon': true,
            'sw-customer-address-form': await Shopware.Component.build('sw-customer-address-form'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-container': true,
            'sw-text-field': true,
            'sw-entity-single-select': true,
            'sw-customer-address-form-options': true,
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
                    get: () => Promise.resolve({
                        id: '63e27affb5804538b5b06cb4e344b130',
                        addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, [
                            {
                                street: 'Stehr Divide',
                                zipcode: '64885-2245',
                                city: 'Faheyshire',
                                id: '652e9e571cc94bd898077f256dcf629f',
                            },
                        ]),
                    }),
                    create: () => ({
                        _isNew: true,
                    }),
                }),
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
        },
        propsData: {
            address: {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                id: '38e8895864a649a1b2ec806dad02ab87',
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
        Shopware.State.registerModule('swOrderDetail', {
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
                order: {
                    addresses: [{
                        street: 'Denesik Bridge',
                        zipcode: '05132',
                        city: 'Bernierstad',
                        id: '38e8895864a649a1b2ec806dad02ab87',
                    }],
                    billingAddressId: '38e8895864a649a1b2ec806dad02ab87',
                    orderCustomer: {
                        customerId: '63e27affb5804538b5b06cb4e344b130',
                    },
                },
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');

        const selectEdit = wrapper.find('.sw-select-option--0');

        await selectEdit.find('.sw-context-menu-item').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.currentAddress).toEqual({
            street: 'Denesik Bridge',
            zipcode: '05132',
            city: 'Bernierstad',
            id: '38e8895864a649a1b2ec806dad02ab87',
        });
    });

    it('should be able to change the address', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        expect(addressSelection.find('.sw-single-select__selection-text').text())
            .toBe('Denesik Bridge, 05132 Bernierstad');

        await addressSelection.find('.sw-select__selection').trigger('click');

        const select = wrapper.find('.sw-select-option--1');

        await select.trigger('click');

        expect(wrapper.emitted('change-address')).toBeTruthy();
        expect(wrapper.emitted('change-address')[0]).toEqual([{
            orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
            customerAddressId: '652e9e571cc94bd898077f256dcf629f',
            type: 'billing',
            edited: false,
        }]);
    });

    it('should be able to create new address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');

        const newAddress = wrapper.find('.sw-select-result-list__content ul:nth-of-type(1)');

        await newAddress.find('.sw-select-result__add-new-address').trigger('click');

        expect(wrapper.vm.currentAddress._isNew).toBe(true);
        expect(wrapper.vm.currentAddress.customerId).toBe('63e27affb5804538b5b06cb4e344b130');
        expect(wrapper.find('.sw-customer-address-form')).toBeTruthy();
    });

    it('should be able to get the options with props', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');

        const list = wrapper.find('.sw-select-result-list__item-list');

        expect(list.findAll('.sw-select-result')).toHaveLength(2);

        const firstSelection = list.findAll('.sw-select-result').at(0).find('.sw-order-address-selection__information');
        expect(firstSelection.findAll('p').at(1).text()).toBe('Denesik Bridge');
        expect(firstSelection.findAll('p').at(2).text()).toBe('05132 Bernierstad');

        const secondSelection = list.findAll('.sw-select-result').at(1).find('.sw-order-address-selection__information');
        expect(secondSelection.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(secondSelection.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
    });

    it('should be able to get the options with not props', async () => {
        wrapper = await createWrapper({
            address: null,
            addressId: null,
        });

        await wrapper.vm.$nextTick();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');

        const list = wrapper.find('.sw-select-result-list__item-list');

        const information = list.findAll('.sw-select-result').at(0).find('.sw-order-address-selection__information');

        expect(list.findAll('.sw-select-result')).toHaveLength(1);
        expect(information.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(information.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
    });
});
