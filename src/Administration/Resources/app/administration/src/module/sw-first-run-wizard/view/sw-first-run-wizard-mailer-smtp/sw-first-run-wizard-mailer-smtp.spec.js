/**
 * @package checkout
 */
import { mount } from '@vue/test-utils';

/**
 * @package checkout
 * @group disabledCompat
 */
describe('module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-smtp', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-first-run-wizard-mailer-smtp', { sync: true }), {
            global: {
                stubs: {
                    'sw-settings-mailer-smtp': {
                        template: '<div />',
                    },
                    'sw-loader': {
                        template: '<div />',
                    },
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
                            'core.mailerSettings.disableDelivery': false,
                        }),
                        saveValues: () => Promise.resolve(),
                    },
                },
            },
        });
    }

    beforeAll(() => {
        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
                app: {
                    config: {
                        settings: {
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                    authToken: {
                        token: 'testToken',
                    },
                },
            },
        });
    });

    it('template renders with disabled extension management', async () => {
        Shopware.State.get('context').app.config.settings.disableExtensionManagement = true;

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-first-run-wizard-mailer-smtp').exists()).toBe(true);

        expect(wrapper.vm.nextAction).toBe('sw.first.run.wizard.index.shopware.account');

        Shopware.State.get('context').app.config.settings.disableExtensionManagement = false;
    });

    it('should emit the button config and the title on creation', async () => {
        const frwMailerSmtp = await createWrapper();
        await flushPromises();

        const buttonConfig = frwMailerSmtp.vm.buttonConfig;
        const title = 'sw-first-run-wizard.mailerSelection.modalTitle';

        const emittedValues = frwMailerSmtp.emitted();

        // remove the action property from the emitted value and the buttonConfig because it is a function and cannot be compared with toEqual and toStrictEqual
        delete emittedValues['buttons-update'][0][0][2].action;
        delete buttonConfig[2].action;
        expect(emittedValues['buttons-update'][0][0]).toStrictEqual(buttonConfig);

        expect(emittedValues['frw-set-title'][0][0]).toStrictEqual(title);
    });

    it('should load the mailerSettings on creation', async () => {
        const frwMailerSmtp = await createWrapper();
        await flushPromises();

        const spyLoadMailer = jest.spyOn(frwMailerSmtp.vm, 'loadMailerSettings');

        await frwMailerSmtp.vm.createdComponent();

        expect(spyLoadMailer).toHaveBeenCalled();
    });

    it('should assign the loaded mailerSettings', async () => {
        const frwMailerSmtp = await createWrapper();
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

        frwMailerSmtp.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);

        await frwMailerSmtp.vm.createdComponent();

        expect(frwMailerSmtp.vm.mailerSettings).toStrictEqual(expectedMailerSettings);
    });

    it('should call the saveValues function', async () => {
        const frwMailerSmtp = await createWrapper();
        await flushPromises();

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
            'core.mailerSettings.disableDelivery': true,
        };

        frwMailerSmtp.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);
        await frwMailerSmtp.vm.createdComponent();

        expect(spySaveValues).not.toHaveBeenCalledWith(expectedMailerSettings);
        await frwMailerSmtp.vm.saveMailerSettings();
        expect(spySaveValues).toHaveBeenCalledWith(expectedMailerSettings);
    });
});
