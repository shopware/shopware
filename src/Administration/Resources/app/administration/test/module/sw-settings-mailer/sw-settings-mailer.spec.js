import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-mailer/page/sw-settings-mailer';
import flushPromises from 'flush-promises';

describe('src/module/sw-settings-mailer/page/sw-settings-mailer', () => {
    const CreateSettingsMailer = function CreateSettingsMailer(emailAgent = null) {
        return shallowMount(Shopware.Component.build('sw-settings-mailer'), {
            stubs: {
                'sw-page': {
                    template: '<div />'
                }
            },
            provide: {
                systemConfigApiService: {
                    getValues: () => Promise.resolve({
                        'core.mailerSettings.emailAgent': emailAgent,
                        'core.mailerSettings.host': null,
                        'core.mailerSettings.port': null,
                        'core.mailerSettings.username': null,
                        'core.mailerSettings.password': null,
                        'core.mailerSettings.encryption': 'null',
                        'core.mailerSettings.authenticationMethod': 'null',
                        'core.mailerSettings.senderAddress': null,
                        'core.mailerSettings.deliveryAddress': null,
                        'core.mailerSettings.disableDelivery': false
                    }),
                    saveValues: () => Promise.resolve()
                }
            }
        });
    };

    it('should be a vue js component', async () => {
        const settingsMailer = new CreateSettingsMailer();

        expect(settingsMailer.vm).toBeTruthy();
    });

    it('should load the mailerSettings on creation', async () => {
        const settingsMailer = new CreateSettingsMailer();
        const spyLoadMailer = jest.spyOn(settingsMailer.vm, 'loadMailerSettings');

        await settingsMailer.vm.createdComponent();

        expect(spyLoadMailer).toHaveBeenCalled();
    });

    it('should assign the loaded mailerSettings', async () => {
        const settingsMailer = new CreateSettingsMailer();

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
            'core.mailerSettings.disableDelivery': true
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);

        await settingsMailer.vm.createdComponent();
        expect(settingsMailer.vm.mailerSettings).toBe(expectedMailerSettings);
    });

    it('should call the saveValues function', async () => {
        const settingsMailer = new CreateSettingsMailer();
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
            'core.mailerSettings.disableDelivery': true
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);
        await settingsMailer.vm.createdComponent();

        expect(spySaveValues).not.toHaveBeenCalledWith(expectedMailerSettings);
        await settingsMailer.vm.saveMailerSettings();
        expect(spySaveValues).toHaveBeenCalledWith(expectedMailerSettings);
    });

    it('should throw smtp configuration errors', async () => {
        const wrapper = new CreateSettingsMailer('smtp');
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBe(null);
        expect(wrapper.vm.smtpPortError).toBe(null);

        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveMailerSettings();
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBeTruthy();
        expect(wrapper.vm.smtpPortError).toBeTruthy();
        expect(wrapper.vm.createNotificationError).toBeCalledTimes(1);
    });

    it('should reset smtp host error', () => {
        const wrapper = new CreateSettingsMailer();
        wrapper.vm.smtpHostError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpHostError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpHostError();

        expect(wrapper.vm.smtpHostError).toBe(null);
    });

    it('should reset smtp port error', () => {
        const wrapper = new CreateSettingsMailer();
        wrapper.vm.smtpPortError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpPortError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpPortError();

        expect(wrapper.vm.smtpPortError).toBe(null);
    });
});
