import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-selection';

describe('module/sw-first-run-wizard/view/sw-first-run-wizard-modal', () => {
    const frwRedirectSmtp = 'sw.first.run.wizard.index.mailer.smtp';
    const frwRedirectLocal = 'sw.first.run.wizard.index.mailer.local';

    const CreateFirstRunWizardMailerSettings = function CreateFirstRunWizardMailerSettings() {
        return shallowMount(Shopware.Component.build('sw-first-run-wizard-mailer-selection'), {
            stubs: {
                'sw-help-text': {
                    template: '<div />'
                },
                'sw-icon': {
                    template: '<div />'
                },
                'sw-loader': {
                    template: '<div />'
                }
            },
            provide: {
                systemConfigApiService: {
                    saveValues: function saveValues() {
                        return Promise.resolve();
                    }
                }
            }
        });
    };

    it('should be a vue js component', async () => {
        const frwMailerSettings = new CreateFirstRunWizardMailerSettings();

        expect(frwMailerSettings.vm).toBeTruthy();
    });

    it('should emit the button config and the title on creation', async () => {
        const frwMailerSettings = new CreateFirstRunWizardMailerSettings();
        const buttonConfig = frwMailerSettings.vm.buttonConfig;
        const title = 'sw-first-run-wizard.mailerSelection.modalTitle';

        const spyButtonUpdateEmit = jest.spyOn(frwMailerSettings.vm, '$emit');

        expect(spyButtonUpdateEmit).not.toBeCalledWith('buttons-update', buttonConfig);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-set-title', title);

        frwMailerSettings.vm.createdComponent();

        expect(spyButtonUpdateEmit).toBeCalledWith('buttons-update', buttonConfig);
        expect(spyButtonUpdateEmit).toBeCalledWith('frw-set-title', title);
    });

    it('handleSelection: should not emit an redirect when user has not select an mailAgent', async () => {
        const frwMailerSettings = new CreateFirstRunWizardMailerSettings();
        const spyButtonUpdateEmit = jest.spyOn(frwMailerSettings.vm, '$emit');
        await frwMailerSettings.setData({ mailAgent: '' });

        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectLocal);

        await frwMailerSettings.vm.handleSelection();
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectLocal);
    });

    it('handleSelection: should emit redirect to mailer settings when user has select an smtp mailAgent', async () => {
        const frwMailerSettings = new CreateFirstRunWizardMailerSettings();
        const spyButtonUpdateEmit = jest.spyOn(frwMailerSettings.vm, '$emit');
        await frwMailerSettings.setData({ mailAgent: 'smtp' });

        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectLocal);

        await frwMailerSettings.vm.handleSelection();
        expect(spyButtonUpdateEmit).toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectLocal);
    });

    it('handleSelection: should emit redirect to paypal when user has select an local mailAgent', async () => {
        const frwMailerSettings = new CreateFirstRunWizardMailerSettings();
        const spyButtonUpdateEmit = jest.spyOn(frwMailerSettings.vm, '$emit');
        await frwMailerSettings.setData({ mailAgent: 'local' });

        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectLocal);

        await frwMailerSettings.vm.handleSelection();
        expect(spyButtonUpdateEmit).not.toBeCalledWith('frw-redirect', frwRedirectSmtp);
        expect(spyButtonUpdateEmit).toBeCalledWith('frw-redirect', frwRedirectLocal);
    });
});
