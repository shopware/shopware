/**
 * @package admin
 */

import 'src/app/component/base/sw-address';
import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
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
    let wrapper;

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
        await wrapper.setProps({
            formattingAddress: 'Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)<br><br>',
        });

        const formattingAddress = wrapper.find('.sw-address__formatting');

        expect(formattingAddress).toBeTruthy();
        expect(formattingAddress.text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
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
