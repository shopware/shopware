import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-smtp';

describe('module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-smtp', () => {
    const CreateFirstRunWizardMailerSmtp = function CreateFirstRunWizardMailerSmtp() {
        return shallowMount(Shopware.Component.build('sw-first-run-wizard-mailer-smtp'), {
            stubs: {
                'sw-settings-mailer-smtp': {
                    template: '<div />'
                },
                'sw-loader': {
                    template: '<div />'
                }
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

    it('should be a vue js component', async () => {
        const frwMailerSmtp = new CreateFirstRunWizardMailerSmtp();

        expect(frwMailerSmtp.vm).toBeTruthy();
    });

    it('should emit the button config and the title on creation', async () => {
        const frwMailerSmtp = new CreateFirstRunWizardMailerSmtp();
        const buttonConfig = frwMailerSmtp.vm.buttonConfig;
        const title = 'sw-first-run-wizard.mailerSelection.modalTitle';

        const spyButtonUpdateEmit = jest.spyOn(frwMailerSmtp.vm, '$emit');

        expect(spyButtonUpdateEmit).not.toBeCalledWith('buttons-update', buttonConfig);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-set-title', title);

        frwMailerSmtp.vm.createdComponent();

        expect(spyButtonUpdateEmit).toBeCalledWith('buttons-update', buttonConfig);
        expect(spyButtonUpdateEmit).toBeCalledWith('frw-set-title', title);
    });

    it('should load the mailerSettings on creation', async () => {
        const frwMailerSmtp = new CreateFirstRunWizardMailerSmtp();
        const spyLoadMailer = jest.spyOn(frwMailerSmtp.vm, 'loadMailerSettings');

        await frwMailerSmtp.vm.createdComponent();

        expect(spyLoadMailer).toHaveBeenCalled();
    });

    it('should assign the loaded mailerSettings', async () => {
        const frwMailerSmtp = new CreateFirstRunWizardMailerSmtp();

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

        frwMailerSmtp.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);

        await frwMailerSmtp.vm.createdComponent();
        expect(frwMailerSmtp.vm.mailerSettings).toBe(expectedMailerSettings);
    });

    it('should call the saveValues function', async () => {
        const frwMailerSmtp = new CreateFirstRunWizardMailerSmtp();
        const spySaveValues = jest.spyOn(frwMailerSmtp.vm.systemConfigApiService, 'saveValues');

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

        frwMailerSmtp.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);
        await frwMailerSmtp.vm.createdComponent();

        expect(spySaveValues).not.toHaveBeenCalledWith(expectedMailerSettings);
        await frwMailerSmtp.vm.saveMailerSettings();
        expect(spySaveValues).toHaveBeenCalledWith(expectedMailerSettings);
    });
});
