import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-datepicker';

function createWrapper(customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-datepicker'), {
        sync: false,
        stubs: {
            'sw-contextual-field': {
                template: `
                <div class="sw-contextual-field">
                    <slot name="sw-field-input"></slot>
                    <slot name="sw-contextual-field-suffix"></slot>
                </div>`
            },
            'sw-icon': true
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
});
