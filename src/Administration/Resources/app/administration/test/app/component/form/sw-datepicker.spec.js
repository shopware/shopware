import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-datepicker';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import flushPromises from 'flush-promises';

function createWrapper(customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-datepicker'), {
        sync: false,
        stubs: {
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-icon': true,
            'sw-field-error': true
        },
        ...customOptions
    });
}


describe('src/app/component/form/sw-datepicker', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled links', async () => {
        wrapper = createWrapper();
        const contextualField = wrapper.find('.sw-contextual-field');
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(contextualField.attributes().disabled).toBeUndefined();
        expect(flatpickrInput.attributes().disabled).toBeUndefined();
    });

    it('should show the dateformat, when no placeholderText is provided', async () => {
        wrapper = createWrapper();
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe('Y-m-d');
    });

    it('should show the placeholderText, when provided', async () => {
        const placeholderText = 'Stop! Hammertime!';
        wrapper = createWrapper({
            propsData: {
                placeholderText
            }
        });
        const flatpickrInput = wrapper.find('.flatpickr-input');

        expect(flatpickrInput.attributes().placeholder).toBe(placeholderText);
    });

    it('should use the admin locale', async () => {
        Shopware.State.get('session').currentLocale = 'de-DE';
        wrapper = createWrapper();
        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('de');

        Shopware.State.get('session').currentLocale = 'en-GB';
        await flushPromises();

        expect(wrapper.vm.$data.flatpickrInstance.config.locale).toBe('en');
    });

    it('should show the label from the property', () => {
        wrapper = createWrapper({
            propsData: {
                label: 'Label from prop'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from prop');
    });

    it('should show the value from the label slot', () => {
        wrapper = createWrapper({
            propsData: {
                label: 'Label from prop'
            },
            scopedSlots: {
                label: '<template>Label from slot</template>'
            }
        });

        expect(wrapper.find('label').text()).toEqual('Label from slot');
    });
});
