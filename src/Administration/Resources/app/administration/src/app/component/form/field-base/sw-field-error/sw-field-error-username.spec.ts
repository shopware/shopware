/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from 'src/app/snippet/en.json';
import de from 'src/app/snippet/de.json';

describe('username whitespace validation message', () => {
    it.each([
        ['en-GB', 'The username must not contain leading or trailing whitespace.'],
        ['de-DE', 'Der Benutzername darf keine führenden oder nachfolgenden Leerzeichen enthalten.'],
    ])('renders the localized API error in %s', async (locale, expectedMessage) => {
        const i18n = createI18n({
            legacy: false,
            locale,
            messages: { 'en-GB': en, 'de-DE': de },
        });
        const wrapper = mount(await wrapTestComponent('sw-field-error', { sync: true }), {
            props: {
                error: {
                    code: 'USER__USERNAME_WHITESPACE',
                    detail: 'Untranslated API error',
                },
            },
            global: {
                mocks: { $t: i18n.global.t },
            },
        });

        expect(wrapper.text()).toBe(expectedMessage);
        wrapper.unmount();
    });
});
