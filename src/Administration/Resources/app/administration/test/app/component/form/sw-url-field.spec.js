import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-url-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/filter/unicode-uri';

describe('components/form/sw-url-field', () => {
    let wrapper;

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.filter('unicodeUri', Shopware.Filter.getByName('unicodeUri'));

        wrapper = shallowMount(Shopware.Component.build('sw-url-field'), {
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
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should validate the url correctly', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080/foobar:foo');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        await wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de:8080:');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);

        await wrapper.find('.sw-url-input-field__input').setValue('#');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);

        await wrapper.find('.sw-url-input-field__input').setValue(':');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);
    });

    it('should set the urlPrefix correctly', async () => {
        expect(wrapper.text()).toBe('https://');
        await wrapper.find('.sw-field__url-input__prefix').trigger('click');
        expect(wrapper.text()).toBe('http://');
    });

    it('should display unicode format', async () => {
        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/blä');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/blä');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/bl%C3%A4');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/😋');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/😋');
        expect(wrapper.vm.currentValue).toBe('www.täst-shöp.de/%F0%9F%98%8B');
    });
});
