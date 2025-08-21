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
                        'mt-text-field': {
                            template:
                                '<input :value="modelValue" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                            props: [
                                'modelValue',
                                'label',
                                'placeholder',
                                'error',
                                'required',
                                'validation',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'mt-number-field': {
                            template:
                                '<input type="number" :value="modelValue" :max="max" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                            props: [
                                'modelValue',
                                'label',
                                'placeholder',
                                'error',
                                'required',
                                'max',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'mt-password-field': {
                            template:
                                '<input type="password" :value="modelValue" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                            props: [
                                'modelValue',
                                'label',
                                'placeholder',
                                'required',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'mt-switch': {
                            template:
                                '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                            props: [
                                'modelValue',
                                'label',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'sw-single-select': {
                            template:
                                '<select :aria-label="label"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                            props: [
                                'value',
                                'label',
                                'placeholder',
                                'options',
                                'required',
                            ],
                            emits: ['update:value'],
                        },
                        'sw-field-error': true,
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

    it('should render host field with correct value and label', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.host': 'https://example.com',
        });
        await flushPromises();

        const hostField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.host"]');
        expect(hostField.exists()).toBe(true);
        expect(hostField.element.value).toBe('https://example.com');
    });

    it('should render port field with correct value and validation', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.port': 476,
        });
        await flushPromises();

        const portField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.port"]');
        expect(portField.exists()).toBe(true);
        expect(portField.element.value).toBe('476');
        expect(portField.attributes('max')).toBe('65536');
    });

    it('should render username and password fields for non-OAuth mode', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp',
            'core.mailerSettings.username': 'test@example.com',
            'core.mailerSettings.password': 'secret',
        });
        await flushPromises();

        const usernameField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.username"]');
        const passwordField = wrapper.find('input[type="password"]');

        expect(usernameField.exists()).toBe(true);
        expect(passwordField.exists()).toBe(true);
        expect(usernameField.element.value).toBe('test@example.com');
    });

    it('should hide username and password fields for OAuth mode', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
        });
        await flushPromises();

        const usernameField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.username"]');
        const passwordField = wrapper.find('input[type="password"][aria-label="sw-settings-mailer.card-smtp.password"]');

        expect(usernameField.exists()).toBe(false);
        expect(passwordField.exists()).toBe(false);
    });

    it('should render OAuth-specific fields when in OAuth mode', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthUrl': 'https://oauth.example.com',
            'core.mailerSettings.oauthScope': 'mail.send',
            'core.mailerSettings.clientId': 'test-client-id',
        });
        await flushPromises();

        // OAuth grant type selector should be visible
        const grantTypeSelect = wrapper.find('select[aria-label="sw-settings-mailer.card-smtp.oauth-grant-type"]');
        expect(grantTypeSelect.exists()).toBe(true);

        // OAuth URL field should be visible
        const oauthUrlField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.oauth-url"]');
        expect(oauthUrlField.exists()).toBe(true);
        expect(oauthUrlField.element.value).toBe('https://oauth.example.com');

        // OAuth scope field should be visible
        const oauthScopeField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.oauth-scope"]');
        expect(oauthScopeField.exists()).toBe(true);
        expect(oauthScopeField.element.value).toBe('mail.send');

        // Client ID field should be visible
        const clientIdField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.oauth-client-id"]');
        expect(clientIdField.exists()).toBe(true);
        expect(clientIdField.element.value).toBe('test-client-id');
    });

    it('should render client secret field for client credentials grant type', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthGrantType': 'client_credentials',
            'core.mailerSettings.clientSecret': 'secret-key',
        });
        await flushPromises();

        const clientSecretField = wrapper.find(
            'input[type="password"][aria-label="sw-settings-mailer.card-smtp.oauth-client-secret"]',
        );
        expect(clientSecretField.exists()).toBe(true);
        expect(clientSecretField.element.value).toBe('secret-key');
    });

    it('should render OAuth username and password fields for ROPC grant type', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthGrantType': 'password',
            'core.mailerSettings.oauthUsername': 'oauth@example.com',
            'core.mailerSettings.oauthPassword': 'oauth-pass',
        });
        await flushPromises();

        const oauthUsernameField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.oauth-username"]');
        const oauthPasswordField = wrapper.find(
            'input[type="password"][aria-label="sw-settings-mailer.card-smtp.oauth-password"]',
        );

        expect(oauthUsernameField.exists()).toBe(true);
        expect(oauthPasswordField.exists()).toBe(true);
        expect(oauthUsernameField.element.value).toBe('oauth@example.com');
    });

    it('should hide OAuth ROPC fields for client credentials grant type', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp+oauth',
            'core.mailerSettings.oauthGrantType': 'client_credentials',
        });
        await flushPromises();

        const oauthUsernameField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.oauth-username"]');
        const oauthPasswordField = wrapper.find(
            'input[type="password"][aria-label="sw-settings-mailer.card-smtp.oauth-password"]',
        );

        expect(oauthUsernameField.exists()).toBe(false);
        expect(oauthPasswordField.exists()).toBe(false);
    });

    it('should render encryption selector with correct options', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.encryption': 'tls',
        });
        await flushPromises();

        const encryptionSelect = wrapper.find('select[aria-label="sw-settings-mailer.card-smtp.encryption"]');
        expect(encryptionSelect.exists()).toBe(true);
    });

    it('should render sender and delivery address fields with email validation', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.senderAddress': 'sender@example.com',
            'core.mailerSettings.deliveryAddress': 'delivery@example.com',
        });
        await flushPromises();

        const senderField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.sender-address"]');
        const deliveryField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.delivery-address"]');

        expect(senderField.exists()).toBe(true);
        expect(deliveryField.exists()).toBe(true);
        expect(senderField.element.value).toBe('sender@example.com');
        expect(deliveryField.element.value).toBe('delivery@example.com');
    });

    it('should render disable delivery switch', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.disableDelivery': true,
        });
        await flushPromises();

        const disableDeliverySwitch = wrapper.find('input[type="checkbox"]');
        expect(disableDeliverySwitch.exists()).toBe(true);
        expect(disableDeliverySwitch.element.checked).toBe(true);
    });

    it('should emit events when host and port values change', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.host': 'initial-host',
            'core.mailerSettings.port': 123,
        });
        await flushPromises();

        const hostField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.host"]');
        const portField = wrapper.find('input[aria-label="sw-settings-mailer.card-smtp.port"]');

        await hostField.setValue('new-host');
        await portField.setValue('456');

        expect(wrapper.emitted()['host-changed']).toBeTruthy();
        expect(wrapper.emitted()['port-changed']).toBeTruthy();
    });
});
