/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-mailer/page/sw-settings-mailer', () => {
    const CreateSettingsMailer = async function CreateSettingsMailer(emailAgent = null) {
        return mount(
            await wrapTestComponent('sw-settings-mailer', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'sw-page': {
                            template:
                                '<div class="sw-page"><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                        },
                        'sw-button-process': {
                            template:
                                '<button class="sw-button" :disabled="isLoading" @click="$emit(\'click\')">{{ $slots.default ? $slots.default()[0].children : "" }}</button>',
                            props: [
                                'isLoading',
                                'processSuccess',
                                'variant',
                            ],
                            emits: [
                                'click',
                                'update:processSuccess',
                            ],
                        },
                        'sw-skeleton': true,
                        'mt-select': {
                            template:
                                '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                            props: [
                                'modelValue',
                                'options',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'sw-radio-field': {
                            template:
                                '<div><input v-for="option in options" :key="option.value" type="radio" :value="option.value" :checked="value === option.value" @change="$emit(\'update:value\', $event.target.value)" /></div>',
                            props: [
                                'value',
                                'options',
                            ],
                            emits: ['update:value'],
                        },
                        'mt-switch': {
                            template:
                                '<input type="checkbox" :checked="modelValue" :label="label" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                            props: [
                                'modelValue',
                                'label',
                            ],
                            emits: ['update:modelValue'],
                        },
                        'sw-settings-mailer-smtp': {
                            template: '<div class="sw-settings-mailer-smtp">SMTP Component</div>',
                            props: [
                                'mailerSettings',
                                'hostError',
                                'portError',
                            ],
                            emits: [
                                'host-changed',
                                'port-changed',
                            ],
                        },
                        'sw-card-view': {
                            template: '<div class="sw-card-view"><slot /></div>',
                        },
                        'mt-card': {
                            template: '<div class="mt-card" :title="title"><slot /></div>',
                            props: [
                                'title',
                                'positionIdentifier',
                                'isLoading',
                            ],
                        },
                        'mt-icon': true,
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
                    provide: {
                        systemConfigApiService: {
                            getValues: () =>
                                Promise.resolve({
                                    'core.mailerSettings.emailAgent': emailAgent,
                                    'core.mailerSettings.host': null,
                                    'core.mailerSettings.port': null,
                                    'core.mailerSettings.username': null,
                                    'core.mailerSettings.password': null,
                                    'core.mailerSettings.encryption': 'null',
                                    'core.mailerSettings.senderAddress': null,
                                    'core.mailerSettings.deliveryAddress': null,
                                    'core.mailerSettings.disableDelivery': false,
                                    'core.mailerSettings.oauthGrantType': 'client_credentials',
                                    'core.mailerSettings.oauthUsername': null,
                                    'core.mailerSettings.oauthPassword': null,
                                }),
                            saveValues: () => Promise.resolve(),
                        },
                        validationService: {},
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
        const settingsMailer = await new CreateSettingsMailer();

        expect(settingsMailer.vm).toBeTruthy();
    });

    it('should render the page header with correct title', async () => {
        const wrapper = await new CreateSettingsMailer();
        await flushPromises();

        const pageTitle = wrapper.find('h2');
        expect(pageTitle.exists()).toBe(true);
        expect(pageTitle.text()).toContain('sw-settings.index.title');
        expect(pageTitle.text()).toContain('sw-settings-mailer.general.textHeadline');
    });

    it('should render the save button', async () => {
        const wrapper = await new CreateSettingsMailer();
        await flushPromises();

        const saveButton = wrapper.find('sw-button-process, .sw-button');
        expect(saveButton.exists()).toBe(true);
        expect(saveButton.text()).toBe('sw-settings-mailer.general.buttonSave');
    });

    it('should render the email agent selector with correct options', async () => {
        const wrapper = await new CreateSettingsMailer();
        await flushPromises();

        const agentSelector = wrapper.find('select');
        expect(agentSelector.exists()).toBe(true);
    });

    it('should display first configuration message when no email agent is set', async () => {
        const wrapper = await new CreateSettingsMailer(null);
        await flushPromises();

        const firstConfigMessage = wrapper.find('.sw-settings-mailer__first-configuration');
        expect(firstConfigMessage.exists()).toBe(true);

        const headline = firstConfigMessage.find('h4');
        expect(headline.text()).toContain('sw-settings-mailer.first-configuration.headline');

        const description = firstConfigMessage.find('p');
        expect(description.text()).toContain('sw-settings-mailer.first-configuration.description');
    });

    it('should hide first configuration message when email agent is set', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        await flushPromises();

        const firstConfigMessage = wrapper.find('.sw-settings-mailer__first-configuration');
        expect(firstConfigMessage.exists()).toBe(false);
    });

    it('should render sendmail options for local email agent', async () => {
        const wrapper = await new CreateSettingsMailer('local');
        await flushPromises();

        // Set email agent to local to trigger sendmail options
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
            },
        });
        await flushPromises();

        // Look for radio input elements
        const radioInputs = wrapper.findAll('input[type="radio"]');
        expect(radioInputs.length).toBeGreaterThan(0);
    });

    it('should render disable delivery switch for non-SMTP modes', async () => {
        const wrapper = await new CreateSettingsMailer('local');
        await flushPromises();

        // Set to local mode to see disable delivery switch
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
            },
        });
        await flushPromises();

        const disableDeliverySwitch = wrapper.find('input[type="checkbox"]');
        expect(disableDeliverySwitch.exists()).toBe(true);
        expect(disableDeliverySwitch.attributes('label')).toBe('sw-settings-mailer.card-smtp.disable-delivery');
    });

    it('should show SMTP card when SMTP mode is selected', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');

        // Set SMTP mode explicitly
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'smtp',
            },
        });
        await flushPromises();

        const smtpCard = wrapper.find('.mt-card[title="SMTP server"]');
        expect(smtpCard.exists()).toBe(true);

        const smtpComponent = wrapper.find('.sw-settings-mailer-smtp');
        expect(smtpComponent.exists()).toBe(true);
    });

    it('should show SMTP card when OAuth mode is selected', async () => {
        const wrapper = await new CreateSettingsMailer('smtp+oauth');

        // Set OAuth mode explicitly
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'smtp+oauth',
            },
        });
        await flushPromises();

        const smtpCard = wrapper.find('.mt-card[title="SMTP server"]');
        expect(smtpCard.exists()).toBe(true);

        const smtpComponent = wrapper.find('.sw-settings-mailer-smtp');
        expect(smtpComponent.exists()).toBe(true);
    });

    it('should hide SMTP card when local mode is selected', async () => {
        const wrapper = await new CreateSettingsMailer('local');

        // Set local mode explicitly
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
            },
        });
        await flushPromises();

        const smtpCard = wrapper.find('.mt-card[title="SMTP server"]');
        expect(smtpCard.exists()).toBe(false);

        const smtpComponent = wrapper.find('.sw-settings-mailer-smtp');
        expect(smtpComponent.exists()).toBe(false);
    });

    it('should render SMTP component with proper configuration', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');

        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'smtp',
                'core.mailerSettings.host': 'smtp.example.com',
                'core.mailerSettings.port': 587,
            },
        });
        await flushPromises();

        const smtpComponent = wrapper.find('.sw-settings-mailer-smtp');
        expect(smtpComponent.exists()).toBe(true);
        expect(smtpComponent.text()).toBe('SMTP Component');
    });

    it('should have reset error methods for SMTP configuration', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        await flushPromises();

        // Test that error reset methods exist and work
        expect(typeof wrapper.vm.resetSmtpHostError).toBe('function');
        expect(typeof wrapper.vm.resetSmtpPortError).toBe('function');

        // Call methods to ensure they don't throw
        wrapper.vm.resetSmtpHostError();
        wrapper.vm.resetSmtpPortError();
    });

    it('should show validation errors when trying to save incomplete SMTP configuration', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        await flushPromises();

        // Set incomplete SMTP settings
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'smtp',
                'core.mailerSettings.host': '', // Empty host should trigger error
                'core.mailerSettings.port': null, // Empty port should trigger error
            },
        });

        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('sw-button-process, .sw-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
        expect(wrapper.vm.smtpHostError).toBeTruthy();
        expect(wrapper.vm.smtpPortError).toBeTruthy();
    });

    it('should successfully save valid configuration', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        const spySaveValues = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'smtp',
                'core.mailerSettings.host': 'smtp.example.com',
                'core.mailerSettings.port': 587,
                'core.mailerSettings.username': 'user',
                'core.mailerSettings.password': 'pass',
                'core.mailerSettings.encryption': 'tls',
                'core.mailerSettings.senderAddress': 'sender@example.com',
                'core.mailerSettings.deliveryAddress': null,
                'core.mailerSettings.disableDelivery': false,
            },
        });

        const saveButton = wrapper.find('.sw-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(spySaveValues).toHaveBeenCalled();
    });

    it('should render help text explaining mailer configuration', async () => {
        const wrapper = await new CreateSettingsMailer();
        await flushPromises();

        // Find all p elements and look for the one with helpText
        const helpText = wrapper.findAll('p').filter((p) => p.text().includes('sw-settings-mailer.helpText'));
        expect(helpText.length).toBeGreaterThan(0);
    });

    it('should show configuration content when not loading', async () => {
        const wrapper = await new CreateSettingsMailer();
        await flushPromises();

        // Should show the main configuration card
        const card = wrapper.find('.mt-card');
        expect(card.exists()).toBe(true);
        expect(card.attributes('title')).toBe('sw-settings-mailer.mailer-configuration.card-title');

        // Should show the email agent selector
        const agentSelector = wrapper.find('select');
        expect(agentSelector.exists()).toBe(true);
    });
});
