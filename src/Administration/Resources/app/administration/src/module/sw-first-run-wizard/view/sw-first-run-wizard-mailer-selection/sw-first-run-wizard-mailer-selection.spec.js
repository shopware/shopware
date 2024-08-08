/**
 * @package checkout
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-first-run-wizard-mailer-selection', { sync: true }), {
        global: {
            stubs: {
                'sw-help-text': {
                    template: '<div class="sw-help-text"></div>',
                },
                'sw-icon': {
                    template: '<div class="sw-icon"></div>',
                },
                'sw-loader': {
                    template: '<div class="sw-loader"></div>',
                },
            },
            provide: {
                systemConfigApiService: {
                    saveValues: function saveValues() {
                        return Promise.resolve();
                    },
                },
            },
        },
    });
}

describe('module/sw-first-run-wizard/view/sw-first-run-wizard-modal', () => {
    const frwRedirectSmtp = 'sw.first.run.wizard.index.mailer.smtp';
    const frwRedirectLocal = 'sw.first.run.wizard.index.mailer.local';

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

    it('should emit the button config and the title on creation', async () => {
        const wrapper = await createWrapper();

        const buttonConfig = wrapper.vm.buttonConfig;
        const title = 'sw-first-run-wizard.mailerSelection.modalTitle';

        expect(wrapper.emitted('buttons-update')).toHaveLength(1);
        expect(wrapper.emitted('buttons-update').at(0).at(0)).toBe(buttonConfig);
        expect(wrapper.emitted('frw-set-title')).toHaveLength(1);
        expect(wrapper.emitted('frw-set-title').at(0).at(0)).toBe(title);
    });

    it('handleSelection: should not emit an redirect when user has not select an mailAgent', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ mailAgent: '' });

        expect(wrapper.emitted('frw-redirect')).toBeUndefined();

        await wrapper.vm.handleSelection();

        expect(wrapper.emitted('frw-redirect')).toBeUndefined();
    });

    it('handleSelection: should emit redirect to mailer settings when user has select an smtp mailAgent', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ mailAgent: 'smtp' });

        expect(wrapper.emitted('frw-redirect')).toBeUndefined();

        await wrapper.vm.handleSelection();

        expect(wrapper.emitted('frw-redirect')).toHaveLength(1);
        expect(wrapper.emitted('frw-redirect').at(0)).toContain(frwRedirectSmtp);
        expect(wrapper.emitted('frw-redirect').at(0)).not.toContain(frwRedirectLocal);
    });

    it('handleSelection: should emit redirect to paypal when user has select an local mailAgent', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ mailAgent: 'local' });

        expect(wrapper.emitted('frw-redirect')).toBeUndefined();

        await wrapper.vm.handleSelection();

        expect(wrapper.emitted('frw-redirect')).toHaveLength(1);
        expect(wrapper.emitted('frw-redirect').at(0)).not.toContain(frwRedirectSmtp);
        expect(wrapper.emitted('frw-redirect').at(0)).toContain(frwRedirectLocal);
    });
});
