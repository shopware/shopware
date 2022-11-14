import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-datepicker';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';

async function createWrapper(customOptions = {}) {
    return shallowMount(await Shopware.Component.build('sw-datepicker'), {
        sync: false,
        stubs: {
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-icon': true,
            'sw-field-error': true
        },
        ...customOptions
    });
}


describe('src/app/component/form/sw-datepicker', () => {
    let wrapper;

    beforeEach(() => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'UTC'
        };
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled links', async () => {
        wrapper = await createWrapper();
        const contextualField = wrapper.find('.sw-contextual-field');
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(contextualField.attributes().disabled).toBeUndefined();
        expect(flatpickrInput.attributes().disabled).toBeUndefined();
    });

    it('should show the dateformat, when no placeholderText is provided', async () => {
        wrapper = await createWrapper();
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe('Y-m-d');
    });

    it('should show the placeholderText, when provided', async () => {
        const placeholderText = 'Stop! Hammertime!';
        wrapper = await createWrapper({
            propsData: {
                placeholderText
            }
        });
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe(placeholderText);
    });

    it('should use the admin locale', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';
        wrapper = await createWrapper();
        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('de');

        Shopware.State.get('session').currentLocale = 'en-GB';
        await flushPromises();

        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('en');
    });

    it('should show the label from the property', async () => {
        wrapper = await createWrapper({
            propsData: {
                label: 'Label from prop'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        wrapper = await createWrapper({
            propsData: {
                label: 'Label from prop'
            },
            scopedSlots: {
                label: '<template>Label from slot</template>'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from slot');
    });

    it('should use UTC for date values', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper({
            propsData: {
                dateType: 'date'
            }
        });

        wrapper.vm.emitValue('2022-01-01T00:00:00');

        expect(wrapper.emitted().input).toBeTruthy();
        expect(wrapper.emitted().input[0]).toEqual(['2022-01-01T00:00:00.000Z']);
    });

    it('should convert datetime values to UTC', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper({
            propsData: {
                dateType: 'datetime'
            }
        });

        wrapper.vm.emitValue('2022-01-01T00:00:00');

        expect(wrapper.emitted().input).toBeTruthy();
        expect(wrapper.emitted().input[0]).toEqual(['2021-12-31T23:00:00.000Z']);
    });

    it('should not show the actual user timezone as a hint when it is not a datetime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper();

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });

    it('should show the UTC timezone as a hint when no timezone was selected and when datetime is datetime', async () => {
        wrapper = await createWrapper({
            propsData: {
                dateType: 'datetime'
            }
        });

        const hint = wrapper.find('.sw-field__hint');
        const clockIcon = hint.find('sw-icon-stub[name="solid-clock"]');

        expect(hint.text()).toContain('UTC');
        expect(clockIcon.isVisible()).toBe(true);
    });

    it('should show the actual user timezone as a hint when datetime is datetime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper({
            propsData: {
                dateType: 'datetime'
            }
        });

        const hint = wrapper.find('.sw-field__hint');
        const clockIcon = hint.find('sw-icon-stub[name="solid-clock"]');

        expect(hint.text()).toContain('Europe/Berlin');
        expect(clockIcon.isVisible()).toBe(true);
    });

    it('should not show the actual user timezone as a hint when the hideHint property is set to true', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper({
            propsData: {
                dateType: 'datetime',
                hideHint: true
            }
        });

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });

    it('should not show the actual user timezone as a hint when hideHint is false and dateType is not dateTime', async () => {
        Shopware.State.get('session').currentUser = {
            timeZone: 'Europe/Berlin'
        };

        wrapper = await createWrapper();

        const hint = wrapper.find('.sw-field__hint');

        expect(hint.exists()).toBe(false);
    });
});
