/**
 * @sw-package checkout
 */

import { DOMWrapper, mount } from '@vue/test-utils';
import type { DefineComponent } from 'vue';
import MtDatepicker from '@shopware-ag/meteor-component-library/dist/esm/MtDatepicker';

async function createWrapper(component: string, customerEditMode = true) {
    const customer = {
        id: 'customer-id',
        birthday: '2000-01-10T00:00:00.000Z',
        group: { translated: { name: 'Customer group' } },
        orderTotalAmount: 0,
        vatIds: [],
    };
    const componentConfig = (await wrapTestComponent(component, { sync: true })) as DefineComponent<{
        customer: typeof customer;
        customerEditMode?: boolean;
    }>;
    const wrapper = mount(componentConfig, {
        props: {
            customer,
            ...(component === 'sw-customer-base-info' ? { customerEditMode } : {}),
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'mt-datepicker': await wrapTestComponent('mt-datepicker', { sync: true }),
                'mt-datepicker-original': MtDatepicker,
                'sw-entity-single-select': true,
                'sw-entity-tag-select': true,
                'sw-single-select': true,
                'sw-time-ago': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        searchIds: () => Promise.resolve({ total: 0 }),
                        get: () => Promise.resolve(),
                    }),
                },
            },
        },
    });

    return { wrapper, customer };
}

describe.each(['Europe/Berlin', 'America/New_York'])('customer birthdays with user timezone %s', (timeZone) => {
    beforeAll(() => {
        Shopware.Store.get('system').registerAdminLocale('de-DE');
    });

    beforeEach(async () => {
        Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        Shopware.Store.get('session').setCurrentUser({ timeZone } as Entity<'user'>);
        await Shopware.Store.get('session').setAdminLocale('de-DE');
    });

    describe.each(['sw-customer-base-info', 'sw-customer-base-form'])('%s', (component) => {
        it.each([
            ['15.01.2000', '2000-01-15T00:00:00.000Z', 'enter'],
            ['15.07.2000', '2000-07-15T00:00:00.000Z', 'enter'],
            ['15.01.2000', '2000-01-15T00:00:00.000Z', 'blur'],
            ['15.07.2000', '2000-07-15T00:00:00.000Z', 'blur'],
        ])('preserves typed date %s as %s on %s', async (inputValue, expectedValue, action) => {
            const { wrapper, customer } = await createWrapper(component);
            await flushPromises();

            const input = wrapper.get<HTMLInputElement>('[data-test-id="dp-input"]');
            await input.setValue(inputValue);

            if (action === 'enter') {
                await input.trigger('keydown', { key: 'Enter', code: 'Enter' });
            } else {
                await input.trigger('blur');
            }
            await flushPromises();

            expect(customer.birthday).toBe(expectedValue);

            await wrapper.setProps({ customer: { ...customer, birthday: expectedValue } });
            await flushPromises();

            expect(input.element.value).toBe(inputValue);
        });

        it('preserves dates selected from the calendar', async () => {
            const { wrapper, customer } = await createWrapper(component);
            await flushPromises();

            await wrapper.get('[data-test-id="dp-input"]').trigger('click');
            await flushPromises();
            await new DOMWrapper(document.body).get('[data-test-id="dp-2000-01-15"]').trigger('click');
            await flushPromises();

            expect(customer.birthday).toBe('2000-01-15T00:00:00.000Z');
        });
    });

    it('displays the birthday as a UTC date outside edit mode', async () => {
        const { wrapper } = await createWrapper('sw-customer-base-info', false);
        await flushPromises();

        expect(wrapper.get('.sw-customer-base__label-birthday').text()).toBe('10/01/2000');
    });
});
