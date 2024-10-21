/**
 * @package services-settings
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
                            template: '<div />',
                        },
                        'sw-icon': true,
                        'sw-button-process': true,
                        'sw-skeleton': true,
                        'sw-select-field': true,
                        'sw-radio-field': true,
                        'sw-switch-field': true,
                        'sw-card': true,
                        'sw-settings-mailer-smtp': true,
                        'sw-card-view': true,
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
                                    'core.mailerSettings.authenticationMethod': 'null',
                                    'core.mailerSettings.senderAddress': null,
                                    'core.mailerSettings.deliveryAddress': null,
                                    'core.mailerSettings.disableDelivery': false,
                                }),
                            saveValues: () => Promise.resolve(),
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

    it('should load the mailerSettings on creation', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        const spyLoadMailer = jest.spyOn(settingsMailer.vm, 'loadMailerSettings');

        await settingsMailer.vm.createdComponent();

        expect(spyLoadMailer).toHaveBeenCalled();
    });

    it('should assign the loaded mailerSettings', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        await flushPromises();

        const expectedMailerSettings = {
            'core.mailerSettings.emailAgent': 'local',
            'core.mailerSettings.host': 'shopware.com',
            'core.mailerSettings.port': 321,
            'core.mailerSettings.username': 'Mad max',
            'core.mailerSettings.password': 'verySafe123',
            'core.mailerSettings.encryption': 'md5',
            'core.mailerSettings.authenticationMethod': 'login',
            'core.mailerSettings.senderAddress': 'sender@address.com',
            'core.mailerSettings.deliveryAddress': 'delivery@address.com',
            'core.mailerSettings.disableDelivery': true,
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);

        await settingsMailer.vm.createdComponent();
        expect(settingsMailer.vm.mailerSettings).toEqual(expectedMailerSettings);
    });

    it('should call the saveValues function', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        const spySaveValues = jest.spyOn(settingsMailer.vm.systemConfigApiService, 'saveValues');

        const expectedMailerSettings = {
            'core.mailerSettings.emailAgent': 'local',
            'core.mailerSettings.host': 'shopware.com',
            'core.mailerSettings.port': 321,
            'core.mailerSettings.username': 'Mad max',
            'core.mailerSettings.password': 'verySafe123',
            'core.mailerSettings.encryption': 'md5',
            'core.mailerSettings.authenticationMethod': 'login',
            'core.mailerSettings.senderAddress': 'sender@address.com',
            'core.mailerSettings.deliveryAddress': 'delivery@address.com',
            'core.mailerSettings.disableDelivery': true,
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);
        await settingsMailer.vm.createdComponent();

        expect(spySaveValues).not.toHaveBeenCalledWith(expectedMailerSettings);
        await settingsMailer.vm.saveMailerSettings();
        expect(spySaveValues).toHaveBeenCalledWith(expectedMailerSettings);
    });

    it('should throw smtp configuration errors', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBeNull();
        expect(wrapper.vm.smtpPortError).toBeNull();

        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveMailerSettings();
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBeTruthy();
        expect(wrapper.vm.smtpPortError).toBeTruthy();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('should reset smtp host error', async () => {
        const wrapper = await new CreateSettingsMailer();
        wrapper.vm.smtpHostError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpHostError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpHostError();

        expect(wrapper.vm.smtpHostError).toBeNull();
    });

    it('should reset smtp port error', async () => {
        const wrapper = await new CreateSettingsMailer();
        wrapper.vm.smtpPortError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpPortError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpPortError();

        expect(wrapper.vm.smtpPortError).toBeNull();
    });
});
