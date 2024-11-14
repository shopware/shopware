/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-url-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/filter/unicode-uri';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-url-field-deprecated', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': await wrapTestComponent('sw-icon'),
                'icons-regular-lock': true,
                'icons-regular-lock-open': true,
                'icons-solid-exclamation-circle': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'mt-icon': true,
                'sw-icon-deprecated': true,
            },
            provide: {
                validationService: {},
            },
        },
        ...additionalOptions,
    });
}

describe('components/form/sw-url-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should validate the url correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.text()).toBe('https://');
        await wrapper.find('.sw-field__url-input__prefix').trigger('click');
        expect(wrapper.text()).toBe('http://');
    });

    it('should display unicode format', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de');
        expect(wrapper.vm.currentUrlValue).toBe('www.täst-shöp.de');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/blä');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/blä');
        expect(wrapper.vm.currentUrlValue).toBe('www.täst-shöp.de/bl%C3%A4');

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/😋');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/😋');
        expect(wrapper.vm.currentUrlValue).toBe('www.täst-shöp.de/%F0%9F%98%8B');
    });

    it('should keep a URL hash', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/#a-hash');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/#a-hash');
        expect(wrapper.vm.currentUrlValue).toBe('www.täst-shöp.de/#a-hash');
    });

    it('should ignore URL hashes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // switch prop to omit hash
        await wrapper.setProps({ omitUrlHash: true });

        await wrapper.find('.sw-url-input-field__input').setValue('www.example.org/#a-hash');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.example.org');
        expect(wrapper.vm.currentUrlValue).toBe('www.example.org');

        // reset component state
        await wrapper.setProps({ omitUrlHash: false });
    });

    it('should keep a URL search', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-url-input-field__input').setValue('www.täst-shöp.de/?a=search');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.täst-shöp.de/?a=search');
        expect(wrapper.vm.currentUrlValue).toBe('www.täst-shöp.de/?a=search');
    });

    it('should ignore a URL search', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // switch prop to omit hash
        await wrapper.setProps({ omitUrlSearch: true });

        await wrapper.find('.sw-url-input-field__input').setValue('www.example.org/?a=search');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
        expect(wrapper.find('.sw-url-input-field__input').element.value).toBe('www.example.org');
        expect(wrapper.vm.currentUrlValue).toBe('www.example.org');

        // reset component state
        await wrapper.setProps({ omitUrlSearch: false });
    });

    it('should show the label from the property', async () => {
        const wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
        });
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
            slots: {
                label: '<template>Label from slot</template>',
            },
        });
        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('properly detects SSL', async () => {
        const SSL_URL = 'https://shopware.com';
        const NON_SSL_URL = 'http://shopware.com';
        const URL_WITHOUT_PROTOCOL = 'shopware.com';

        const wrapper = await createWrapper();

        expect(wrapper.vm.getSSLMode(SSL_URL)).toBeTruthy();
        expect(wrapper.vm.getSSLMode(NON_SSL_URL)).toBeFalsy();
        expect(wrapper.vm.getSSLMode(URL_WITHOUT_PROTOCOL)).toBeFalsy();
    });

    it('removes any protocol', async () => {
        const HTTP_URL = 'http://shopware.com';
        const HTTPS_URL = 'https://shopware.com';
        const FILE_URL = 'file://shopware.com';
        const EXPECTED_URL = 'shopware.com';

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.checkInput(HTTP_URL);
        expect(wrapper.vm.currentUrlValue).toEqual(EXPECTED_URL);

        wrapper.vm.checkInput(HTTPS_URL);
        expect(wrapper.vm.currentUrlValue).toEqual(EXPECTED_URL);

        wrapper.vm.checkInput(FILE_URL);
        expect(wrapper.vm.currentUrlValue).toEqual(EXPECTED_URL);
    });

    it('allows empty values', async () => {
        const INITIAL_URL = 'https://shopware.com';
        const URL_WITHOUT_PROTOCOL = 'shopware.com';
        const EXPECTED_URL = '';

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-url-input-field__input').setValue(INITIAL_URL);
        await wrapper.find('.sw-url-input-field__input').trigger('blur');

        expect(wrapper.vm.currentUrlValue).toBe(URL_WITHOUT_PROTOCOL);
        expect(wrapper.vm.errorUrl).toBeNull();

        await wrapper.find('.sw-url-input-field__input').setValue('');
        await wrapper.find('.sw-url-input-field__input').trigger('blur');

        expect(wrapper.vm.currentUrlValue).toBe(EXPECTED_URL);
        expect(wrapper.vm.errorUrl).toBeNull();
    });

    it('should evaluate ssl protocol correctly at start', async () => {
        const wrapper = await createWrapper({
            props: {
                value: 'http://shopware.com',
            },
        });
        await flushPromises();

        expect(wrapper.vm.sslActive).toBe(false);
    });

    it('should update empty values', async () => {
        const wrapper = await createWrapper({
            props: {
                value: 'https://shopware.com',
            },
        });
        await flushPromises();

        const input = wrapper.find('input');
        expect(input.element.value).toBe('shopware.com');

        await input.setValue('');
        await input.trigger('blur');
        expect(wrapper.vm.currentUrlValue).toBe('');
        expect(wrapper.emitted('update:value')).toStrictEqual([
            ['https://shopware.com'],
            [''],
        ]);
    });
});
