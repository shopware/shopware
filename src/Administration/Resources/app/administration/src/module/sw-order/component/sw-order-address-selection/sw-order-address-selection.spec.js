import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(propsData) {
    return mount(await wrapTestComponent('sw-order-address-selection', { sync: true }), {
        global: {
            directives: {
                popover: {},
            },
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `<li :class="componentClasses" class="sw-select-result" @click.stop="onClickResult">
                        <slot></slot></li>`,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
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
                'sw-popover': await wrapTestComponent('sw-popover', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
                'sw-customer-address-form': await wrapTestComponent('sw-customer-address-form'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-customer-address-form-options': await wrapTestComponent('sw-customer-address-form-options'),
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
        },
        props: {
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

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.findComponent('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

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
