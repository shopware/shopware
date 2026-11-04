/**
 * @sw-package framework
 */

import { DOMWrapper, mount } from '@vue/test-utils';

describe.each(['Europe/Berlin', 'America/New_York'])('date-only fields with user timezone %s', (timeZone) => {
    beforeAll(() => {
        Shopware.Store.get('system').registerAdminLocale('de-DE');
    });

    beforeEach(async () => {
        Shopware.Store.get('session').setCurrentUser({ timeZone });
        await Shopware.Store.get('session').setAdminLocale('de-DE');
    });

    it.each([
        ['15.01.2000', '2000-01-15T00:00:00.000Z', 'enter'],
        ['15.07.2000', '2000-07-15T00:00:00.000Z', 'enter'],
        ['15.01.2000', '2000-01-15T00:00:00.000Z', 'blur'],
        ['15.07.2000', '2000-07-15T00:00:00.000Z', 'blur'],
    ])('preserves typed date %s as %s on %s', async (inputValue, expectedValue, action) => {
        const onUpdate = jest.fn();
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                dateType: 'date',
                modelValue: '2000-01-01T00:00:00.000Z',
                'onUpdate:modelValue': onUpdate,
            },
        });
        await flushPromises();

        const input = wrapper.get<HTMLInputElement>('[data-test-id="dp-input"]');
        await input.setValue(inputValue);

        if (action === 'enter') {
            await input.trigger('keydown', { key: 'Enter', code: 'Enter' });
        } else {
            await input.trigger('blur');
        }
        await flushPromises();

        expect(onUpdate).toHaveBeenLastCalledWith(expectedValue);

        await wrapper.setProps({ modelValue: expectedValue });
        await flushPromises();

        expect(input.element.value).toBe(inputValue);
    });

    it('preserves dates selected from the calendar', async () => {
        const onUpdate = jest.fn();
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                dateType: 'date',
                modelValue: '2000-01-01T00:00:00.000Z',
                'onUpdate:modelValue': onUpdate,
            },
        });
        await flushPromises();

        await wrapper.get('[data-test-id="dp-input"]').trigger('click');
        await flushPromises();
        await new DOMWrapper(document.body).get('[data-test-id="dp-2000-01-15"]').trigger('click');
        await flushPromises();

        expect(onUpdate).toHaveBeenLastCalledWith('2000-01-15T00:00:00.000Z');
    });

    it('still uses the user timezone for datetime fields', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                dateType: 'datetime',
                modelValue: '2000-01-15T00:00:00.000Z',
            },
        });
        await flushPromises();

        expect(wrapper.get<HTMLInputElement>('[data-test-id="dp-input"]').element.value).toBe(
            timeZone === 'Europe/Berlin' ? '15.01.2000, 01:00' : '14.01.2000, 19:00',
        );
        expect(wrapper.get('[data-testid="time-zone-hint"]').text()).toBe(timeZone);
    });
});
