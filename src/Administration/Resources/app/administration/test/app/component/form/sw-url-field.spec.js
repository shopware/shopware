import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-url-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/filter/unicode-uri';

function createWrapper(additionalOptions = {}) {
    const localVue = createLocalVue();
    localVue.filter('unicodeUri', Shopware.Filter.getByName('unicodeUri'));

    return shallowMount(Shopware.Component.build('sw-url-field'), {
        localVue,
        stubs: {
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': Shopware.Component.build('sw-icon'),
            'icons-default-lock-closed': true,
            'icons-default-lock-open': true
        },
        provide: {
            validationService: {}
        },
        ...additionalOptions
    });
}

describe('components/form/sw-url-field', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should validate the url correctly', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080/foobar:foo');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080:');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);

        await wrapper.find('.sw-url-input-field__input').setValue('#');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);

        await wrapper.find('.sw-url-input-field__input').setValue(':');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);
    });

    it('should set the urlPrefix correctly', async () => {
        expect(wrapper.text()).toBe('https://');
        await wrapper.find('.sw-field__url-input__prefix').trigger('click');
        expect(wrapper.text()).toBe('http://');
    });

    it('should display unicode format', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/blä');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/blä');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/bl%C3%A4');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/😋');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/😋');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/%F0%9F%98%8B');
    });

    it('should keep a URL hash', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/#a-hash');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/#a-hash');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/#a-hash');
    });

    it('should ignore URL hashes', async () => {
        // switch prop to omit hash
        await wrapper.setProps({ omitUrlHash: true });

        await wrapper.find('.sw-url-input-field__input').setValue('www.example.org/#a-hash');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.example.org');
        expect(wrapper.vm.currentValue).toBe('www.example.org');

        // reset component state
        await wrapper.setProps({ omitUrlHash: false });
    });

    it('should keep a URL search', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/?a=search');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/?a=search');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/?a=search');
    });

    it('should ignore a URL search', async () => {
        // switch prop to omit hash
        await wrapper.setProps({ omitUrlSearch: true });

        await wrapper.find('.sw-url-input-field__input').setValue('www.example.org/?a=search');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.example.org');
        expect(wrapper.vm.currentValue).toBe('www.example.org');

        // reset component state
        await wrapper.setProps({ omitUrlSearch: false });
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
