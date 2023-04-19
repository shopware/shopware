import { shallowMount } from '@vue/test-utils';
import swCustomerDetailAddresses from 'src/module/sw-customer/view/sw-customer-detail-addresses';

/**
 * @package customer-order
 */

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
                template: '<div><slot></slot><slot name="grid"></slot></div>',
            },
            'sw-field': true,
            'sw-button': true,
            'sw-modal': true,
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
});
