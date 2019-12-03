import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-mailer/page/sw-settings-mailer';

describe('src/module/sw-settings-mailer/page/sw-settings-mailer', () => {
    const CreateSettingsMailer = function CreateSettingsMailer() {
        return shallowMount(Shopware.Component.build('sw-settings-mailer'), {
            stubs: {
                'sw-page': '<div />'
            },
            mocks: {
                $tc: (translationPath) => translationPath
            },
            provide: {
                systemConfigApiService: {
                    getValues: () => Promise.resolve({
                        'core.mailerSettings.emailAgent': null,
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

    it('should be a vue js component', () => {
        const settingsMailer = new CreateSettingsMailer();

        expect(settingsMailer.isVueInstance()).toBeTruthy();
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
});
