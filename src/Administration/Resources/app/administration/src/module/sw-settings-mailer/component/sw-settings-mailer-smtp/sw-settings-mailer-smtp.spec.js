/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-mailer/component/sw-settings-mailer-smtp', () => {
    const createWrapper = async (mailerSettings = {}) => {
        return mount(
            await wrapTestComponent('sw-settings-mailer-smtp', {
                sync: true,
            }),
            {
                props: {
                    mailerSettings,
                },
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        validationService: {},
                    },
                    stubs: {
                        'sw-text-field': await wrapTestComponent('sw-text-field'),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-field-error': true,
                        'sw-single-select': true,

                        'sw-help-text': true,
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                    },
                    mocks: {
                        $tc(translationKey) {
                            return translationKey;
                        },
                    },
                },
            },
        );
    };

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should assign host value', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.host': 'https://example.com',
        });
        await flushPromises();

        const host = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.host"]').element.value;
        expect(host).toBe('https://example.com');
    });

    it('should assign port value', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.port': 476,
        });
        await flushPromises();

        const port = wrapper.findByLabel('sw-settings-mailer.card-smtp.port').element.value;
        expect(port).toBe('476');
    });

    it('should detect OAuth mode correctly', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
        });

        expect(wrapper.vm.isOauth).toBe(true);

        const nonOAuthWrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp',
        });

        expect(nonOAuthWrapper.vm.isOauth).toBe(false);
    });

    it('should detect client credentials grant type correctly', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthGrantType': 'client_credentials',
        });

        expect(wrapper.vm.isClientCredentials).toBe(true);
        expect(wrapper.vm.isROPC).toBe(false);
    });

    it('should default to client credentials when grant type is not set', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
        });

        expect(wrapper.vm.isClientCredentials).toBe(true);
        expect(wrapper.vm.isROPC).toBe(false);
    });

    it('should detect ROPC grant type correctly', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthGrantType': 'password',
        });

        expect(wrapper.vm.isROPC).toBe(true);
        expect(wrapper.vm.isClientCredentials).toBe(false);
    });

    it('should provide OAuth grant type options', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
        });

        expect(wrapper.vm.oauthGrantTypeOptions).toEqual([
            {
                value: 'client_credentials',
                label: 'sw-settings-mailer.oauth-grant-type.client-credentials',
            },
            {
                value: 'password',
                label: 'sw-settings-mailer.oauth-grant-type.password',
            },
        ]);
    });

    it('should provide encryption options', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.encryptionOptions).toEqual([
            {
                value: 'null',
                label: 'sw-settings-mailer.encryption.no-encryption',
            },
            {
                value: 'ssl',
                label: 'sw-settings-mailer.encryption.ssl',
            },
            {
                value: 'tls',
                label: 'sw-settings-mailer.encryption.tls',
            },
        ]);
    });
});
