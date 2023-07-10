import { shallowMount } from '@vue/test-utils';
import swCustomerDetailAddresses from 'src/module/sw-customer/view/sw-customer-detail-addresses';

/**
 * @package customer-order
 */

const { ShopwareError } = Shopware.Classes;

Shopware.Component.register('sw-customer-detail-addresses', swCustomerDetailAddresses);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-customer-detail-addresses'), {
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve([]),
                        create: () => Promise.resolve({ id: '' }),
                    };
                },
            },

        },

        propsData: {
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

        stubs: {
            'sw-card': {
                template: `<div class="sw-card">
                    <slot name="toolbar"></slot>
                    <slot name="grid"></slot>
                    <slot></slot>
                </div>`,
            },
            'sw-card-filter': {
                template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
            },
            'sw-field': true,
            'sw-button': {
                template: '<div class="sw-button" @click="$emit(`click`)"></div>',
            },
            'sw-modal': true,
            'sw-icon': true,
            'sw-one-to-many-grid': {
                props: ['collection'],
                template: `
                    <div>
                        <tbody>
                            <td v-for="item in collection">
                                <slot name="column-lastName" v-bind="{ item }"></slot>
                            </td>
                        </tbody>
                    </div>
                `,
            },
            'sw-customer-address-form': true,
            'sw-customer-address-form-options': true,
        },
    });
}

describe('module/sw-customer/view/sw-customer-detail-addresses.spec.js', () => {
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
        wrapper.vm.salutationRepository.searchIds = jest.fn(() => Promise.resolve({ data: ['1'] }));

        expect(wrapper.vm.currentAddress).toBeNull();

        const swButton = wrapper.find('.sw-button');
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

        expect(Shopware.State.getters['error/getApiError'](entityMock, 'street')).toBeNull();

        await wrapper.vm.onSaveAddress();

        expect(Shopware.State.getters['error/getApiError'](entityMock, 'street')).toBeInstanceOf(ShopwareError);
    });
});
