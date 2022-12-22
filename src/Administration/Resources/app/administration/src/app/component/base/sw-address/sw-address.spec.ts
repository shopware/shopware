/**
 * @package admin
 */

import 'src/app/component/base/sw-address';
import { shallowMount } from '@vue/test-utils';
import type { Wrapper } from '@vue/test-utils';
import type Vue from 'vue';

async function createWrapper(): Promise<Wrapper<Vue>> {
    return shallowMount(await Shopware.Component.build('sw-address'), {
        propsData: {
            address: {
                salutation: 'Mr',
                title: 'Dr.',
                firstName: 'John',
                lastName: 'Doe',
                company: 'Shopware AG',
                street: 'Main Street',
                additionalAddressLine1: 'Floor 23',
                additionalAddressLine2: 'Secret room 1337',
                zipcode: '555 Nase',
                city: 'Miami',
                country: {
                    name: 'USA',
                },
                countryState: {
                    name: 'Florida',
                },
            }
        },
        stubs: {
            'router-link': {
                template: '<a class="router-link" href="#"><slot></slot></a>',
                props: ['to']
            }
        },
        attachTo: document.body,
    });
}

describe('src/app/component/base/sw-address/index.ts', () => {
    let wrapper: Wrapper<Vue>;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises;
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises;
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render an address', async () => {
        expect(wrapper.get('.sw-address__company').text()).toBe('Shopware AG');
        expect(wrapper.get('.sw-address__full-name').text()).toBe('Dr. John Doe');
        expect(wrapper.get('.sw-address__street').text()).toBe('Main Street');
        expect(wrapper.get('.sw-address__additional-line-1').text()).toBe('Floor 23');
        expect(wrapper.get('.sw-address__additional-line-2').text()).toBe('Secret room 1337');
        expect(wrapper.findAll('.sw-address__location span').at(0).text()).toBe('555 Nase');
        expect(wrapper.findAll('.sw-address__location span').at(1).text()).toBe('Miami');
        expect(wrapper.get('.sw-address__country').text()).toBe('USA');
    });

    it('should render address with headline', async () => {
        await wrapper.setProps({
            headline: 'Super cool address'
        });

        expect(wrapper.get('.sw-address__headline').text()).toBe('Super cool address');
    });

    it('should render address with edit button', async () => {
        await wrapper.setProps({
            headline: 'Super cool address',
            showEditButton: true,
            editLink: { path: 'path/edit-address' }
        });

        expect(wrapper.get('.sw-address-headline-link').text()).toBe('global.default.edit');
        expect(wrapper.get('.sw-address-headline-link').props('to')).toEqual({ path: 'path/edit-address' });
    });
});
