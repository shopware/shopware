/**
 * @package admin
 */

import { mount } from '@vue/test-utils_v3';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-datepicker', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-icon': true,
                'sw-field-error': true,
            },
        },
        ...customOptions,
    });
}


describe('src/app/component/form/sw-datepicker', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'UTC',
        };
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
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
        const placeholderText = 'Stop! Hammertime!';
        wrapper = await createWrapper({
            props: {
                placeholderText,
            },
        });
        await flushPromises();

        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe(placeholderText);
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
                    'sw-datepicker': await wrapTestComponent('sw-datepicker', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-icon': true,
                    'sw-field-error': true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('should not show the actual user timezone as a hint when it is not a datetime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

        wrapper = await createWrapper();
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });

    it('should show the UTC timezone as a hint when no timezone was selected and when datetime is datetime', async () => {
        wrapper = await createWrapper({
            props: {
                dateType: 'datetime',
            },
        });
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');
        const clockIcon = hint.find('sw-icon-stub[name="solid-clock"]');

        expect(hint.text()).toContain('UTC');
        expect(clockIcon.isVisible()).toBe(true);
    });

    it('should show the actual user timezone as a hint when datetime is datetime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

        wrapper = await createWrapper({
            props: {
                dateType: 'datetime',
            },
        });
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');
        const clockIcon = hint.find('sw-icon-stub[name="solid-clock"]');

        expect(hint.text()).toContain('Europe/Berlin');
        expect(clockIcon.isVisible()).toBe(true);
    });

    it('should not show the actual user timezone as a hint when the hideHint property is set to true', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

        wrapper = await createWrapper({
            props: {
                dateType: 'datetime',
                hideHint: true,
            },
        });
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });

    it('should not show the actual user timezone as a hint when hideHint is false and dateType is not dateTime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

        wrapper = await createWrapper();
        await flushPromises();

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });

    it('should not convert the date when a timezone is set (type=date)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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

    it('should not emit a converted date when a timezone is set (type=date)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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

    it('should not convert the date when a timezone is set (type=time)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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

    it('should not emit a converted date when a timezone is set (type=time)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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

    it('should convert the date when a timezone is set (type=datetime)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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

    it('should emit a converted date when a timezone is set (type=datetime)', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin',
        };

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
});
