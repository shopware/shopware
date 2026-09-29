/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/meteor-wrapper/mt-datepicker', () => {
    beforeAll(() => {
        Shopware.Store.get('system').registerAdminLocale('de-DE');
        Shopware.Store.get('system').registerAdminLocale('en-GB');
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({
            firstName: 'John',
            lastName: 'Doe',
            timeZone: 'Europe/Berlin',
        });

        Shopware.Store.get('session').setAdminLocale('de-DE');
    });

    it('should use the user timeZone', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        expect(wrapper.find('[data-testid="time-zone-hint"]').text()).toBe('Europe/Berlin');
    });

    it('should use the user locale (de)', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');
        await flushPromises();

        // Expect german locale to be used
        expect(document.body.textContent).toContain('MoDiMiDoFrSaSo');
    });

    it('should use the user locale (en)', async () => {
        // Set the user locale to english
        Shopware.Store.get('session').setAdminLocale('en-GB');

        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');
        await flushPromises();

        // Expect german locale to be used
        expect(document.body.textContent).toContain('MoTuWeThFrSaSu');
    });

    it('should use custom format based on currentLocale (de)', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                modelValue: '2023-10-01T00:00:00+02:00',
            },
        });

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');

        // Expect german locale to be used
        expect(wrapper.find('[data-test-id="dp-input"]').element.value).toBe('01.10.2023, 00:00');
    });

    it('should use custom format based on currentLocale (en)', async () => {
        // Set the user locale to english
        Shopware.Store.get('session').setAdminLocale('en-GB');

        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                modelValue: '2023-10-01T00:00:00+02:00',
            },
        });

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');

        // Expect german locale to be used
        expect(wrapper.find('[data-test-id="dp-input"]').element.value).toBe('01/10/2023, 00:00');
    });

    it('should escape literal text in generated date-fns format', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                format: jest.fn(),
            },
        });

        const dateTimeFormatMock = jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => ({
            resolvedOptions: () => ({ hour12: false }),
            formatToParts: () => [
                { type: 'hour', value: '14' },
                { type: 'literal', value: ' h ' },
                { type: 'minute', value: '30' },
                { type: 'literal', value: " o'clock " },
                { type: 'dayPeriod', value: 'PM' },
            ],
        }));

        expect(wrapper.vm.datePickerFormat).toBe("HH' h 'mm' o''clock 'aa");

        dateTimeFormatMock.mockRestore();
    });
});
