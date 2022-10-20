import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-mailer/component/sw-settings-mailer-smtp';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('src/module/sw-settings-mailer/component/sw-settings-mailer-smtp', () => {
    const createWrapper = (mailerSettings = {}) => {
        return shallowMount(Shopware.Component.build('sw-settings-mailer-smtp'), {
            provide: {
                validationService: {}
            },
            stubs: {
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-number-field': Shopware.Component.build('sw-number-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-switch-field': true,
                'sw-password-field': true,
                'sw-help-text': true,
            },
            mocks: {
                $tc(translationKey) {
                    return translationKey;
                },
            },
            propsData: {
                mailerSettings,
            }
        });
    };

    it('should be a vue js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should assign host value', () => {
        const wrapper = createWrapper({
            'core.mailerSettings.host': 'https://example.com',
        });

        const host = wrapper.find("#sw-field--mailerSettings\\[\\'core-mailerSettings-host\\'\\]").element.value;
        expect(host).toBe('https://example.com');
    });

    it('should assign port value', () => {
        const wrapper = createWrapper({
            'core.mailerSettings.port': 476,
        });

        const port = wrapper.find("#sw-field--mailerSettings\\[\\'core-mailerSettings-port\\'\\]").element.value;
        expect(port).toBe('476');
    });
});
