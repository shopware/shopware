/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-datepicker-deprecated', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-icon': true,
                'sw-field-error': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        ...customOptions,
    });
}


describe('src/app/component/form/sw-datepicker', () => {
    let wrapper;
    const currentUser = Shopware.State.get('session').currentUser;

    beforeEach(async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'UTC' });
    });

    afterAll(() => {
        Shopware.State.commit('setCurrentUser', currentUser);
    });

    it('should have enabled links', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const contextualField = wrapper.find('.sw-contextual-field');
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(contextualField.attributes().disabled).toBeUndefined();
        expect(flatpickrInput.attributes().disabled).toBeUndefined();
    });

    it('should show the dateformat, when no placeholderText is provided', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe('Y-m-d');
    });

    it('should show the placeholderText, when provided', async () => {
        const placeholder = 'Stop! Hammertime!';
        wrapper = await createWrapper({
            props: {
                placeholder,
            },
        });
        await flushPromises();

        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe(placeholder);
    });

    it('should use the admin locale', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('de');

        Shopware.State.get('session').currentLocale = 'en-GB';
        await flushPromises();

        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('en');
    });

    it('should show the label from the property', async () => {
        wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
        });
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        wrapper = await mount({
            template: `
               <sw-datepicker label="Label from prop">
                 <template #label>
                      Label from slot
                 </template>
             </sw-datepicker>`,
        }, {
            global: {
                stubs: {
                    'sw-datepicker': await wrapTestComponent('sw-datepicker-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-icon': true,
                    'sw-field-error': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it.each([
        { dateType: 'date', timeZone: 'UTC', expectedTimeZone: 'UTC' },
        { dateType: 'date', timeZone: 'Europe/Berlin', expectedTimeZone: 'UTC' },
        { dateType: 'time', timeZone: 'UTC', expectedTimeZone: 'UTC' },
        { dateType: 'time', timeZone: 'Europe/Berlin', expectedTimeZone: 'UTC' },
        { dateType: 'datetime', timeZone: 'UTC', expectedTimeZone: 'UTC' },
        { dateType: 'datetime', timeZone: 'Europe/Berlin', expectedTimeZone: 'Europe/Berlin' },
    ])('should show the $expectedTimeZone timezone as a hint when the $timeZone timezone was selected and dateType is $dateType and hideHint is false', async ({ dateType, timeZone, expectedTimeZone }) => {
        Shopware.State.commit('setCurrentUser', { timeZone: timeZone });

        wrapper = await createWrapper({
            props: {
                dateType,
                hideHint: false,
            },
        });
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');
        const clockIcon = hint.find('sw-icon-stub[name="solid-clock"]');

        expect(hint.text()).toContain(expectedTimeZone);
        expect(clockIcon.isVisible()).toBe(true);
    });

    it.each([
        { dateType: 'date', timeZone: 'UTC' },
        { dateType: 'date', timeZone: 'Europe/Berlin' },
        { dateType: 'time', timeZone: 'UTC' },
        { dateType: 'time', timeZone: 'Europe/Berlin' },
        { dateType: 'datetime', timeZone: 'UTC' },
        { dateType: 'datetime', timeZone: 'Europe/Berlin' },
    ])('should show no timezone as a hint when the $timeZone timezone was selected and dateType is $dateType and hideHint is true', async ({ dateType, timeZone }) => {
        Shopware.State.commit('setCurrentUser', { timeZone: timeZone });

        wrapper = await createWrapper({
            props: {
                dateType,
                hideHint: true,
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-field__hint').exists()).toBe(false);
    });

    it('should not convert the date when a timezone is set and dateType is date', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'date',
            },
        });
        await flushPromises();

        // Can't test with DOM because of the flatpickr dependency
        expect(wrapper.vm.timezoneFormattedValue).toBe('2023-03-27T00:00:00.000+00:00');
    });

    it('should not emit a converted date when a timezone is set and dateType is date', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'date',
            },
        });
        await flushPromises();

        // can't test with DOM because of the flatpickr dependency
        wrapper.vm.timezoneFormattedValue = '2023-03-22T00:00:00.000+00:00';

        expect(wrapper.emitted('update:value')[0]).toEqual(['2023-03-22T00:00:00.000+00:00']);
    });

    it('should not convert the date when a timezone is set and dateType is time', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'time',
            },
        });
        await flushPromises();

        // Can't test with DOM because of the flatpickr dependency
        expect(wrapper.vm.timezoneFormattedValue).toBe('2023-03-27T00:00:00.000+00:00');
    });

    it('should not emit a converted date when a timezone is set and dateType is time', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'time',
            },
        });
        await flushPromises();

        // can't test with DOM because of the flatpickr dependency
        wrapper.vm.timezoneFormattedValue = '2023-03-22T00:00:00.000+00:00';

        expect(wrapper.emitted('update:value')[0]).toEqual(['2023-03-22T00:00:00.000+00:00']);
    });

    it('should convert the date when a timezone is set and dateType is dateTime', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'datetime',
            },
        });
        await flushPromises();

        // Can't test with DOM because of the flatpickr dependency
        expect(wrapper.vm.timezoneFormattedValue).toBe('2023-03-27T02:00:00.000Z');
    });

    it('should emit a converted date when a timezone is set and dateType is dateTime', async () => {
        Shopware.State.commit('setCurrentUser', { timeZone: 'Europe/Berlin' });

        wrapper = await createWrapper({
            props: {
                value: '2023-03-27T00:00:00.000+00:00',
                dateType: 'datetime',
            },
        });
        await flushPromises();

        // can't test with DOM because of the flatpickr dependency
        wrapper.vm.timezoneFormattedValue = '2023-03-22T00:00:00.000+00:00';

        expect(wrapper.emitted('update:value')[0]).toEqual(['2023-03-21T23:00:00.000Z']);
    });


    it('should emit a date when is typed', async () => {
        wrapper = await createWrapper({});
        await flushPromises();

        const input = wrapper.find('.form-control.input');

        await input.trigger('focus');
        input.element.value = '2023-03-27';
        await input.trigger('input');
        await input.trigger('blur');

        expect(wrapper.emitted('update:value')).toHaveLength(1);
    });
});
