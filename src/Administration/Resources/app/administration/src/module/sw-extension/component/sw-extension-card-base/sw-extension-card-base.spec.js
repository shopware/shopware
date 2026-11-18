import { mount } from '@vue/test-utils';
import 'src/module/sw-extension/service';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';

async function createWrapper(propsData = {}, provide = {}) {
    return mount(await wrapTestComponent('sw-extension-card-base', { sync: true }), {
        global: {
            mixins: [Shopware.Mixin.getByName('sw-extension-error')],
            provide: {
                shopwareExtensionService: {
                    getOpenLink: () => null,
                },
                extensionStoreActionService: {},
                cacheApiService: {},
                ...provide,
            },
            stubs: {
                'sw-loader': true,

                'sw-extension-icon': true,
                'sw-context-menu-item': {
                    name: 'sw-context-menu-item',
                    props: ['routerLink'],
                    template: '<div class="sw-context-menu-item"><slot></slot></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-extension-uninstall-modal': true,
                'sw-extension-removal-modal': true,
                'sw-extension-permissions-modal': {
                    template: '<div class="sw-extension-permissions-modal"><slot></slot></div>',
                },
                'sw-extension-privacy-policy-extensions-modal': true,
                'sw-meteor-card': {
                    template: '<div><slot></slot></div>',
                },
                'router-link': true,
                'sw-time-ago': await wrapTestComponent('sw-time-ago', { sync: true }),
            },
        },
        props: {
            extension: { installedAt: null },
            ...propsData,
        },
    });
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    beforeAll(() => {
        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register({
            id: 'context',
            state: () => ({
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
            }),
        });
    });

    beforeEach(() => {
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue({
            $t: (key) => key,
            $router: { push: jest.fn() },
        });
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should show the correct image (icon)', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                icon: 'my-icon',
                permissions: [],
            },
        });

        expect(wrapper.vm.image).toBe('my-icon');
    });

    it('should show the correct image (iconRaw)', async () => {
        const base64Example = 'z87hufieajh38haefwa9hefjio';

        const wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                iconRaw: base64Example,
                permissions: [],
            },
        });

        expect(wrapper.vm.image).toBe(`data:image/png;base64, ${base64Example}`);
    });

    it('should show the correct image (default theme asset)', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                permissions: [],
            },
        });

        expect(wrapper.vm.image).toBe('administration/administration/static/img/theme/default_theme_preview.webp');
    });

    it('should be installed', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                permissions: [],
            },
        });

        expect(wrapper.vm.isInstalled).toBe(true);
    });

    it('should not be installed', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: null,
            },
        });

        expect(wrapper.vm.isInstalled).toBe(false);
    });

    it('should display the purchased date without time', async () => {
        const formatDateSpy = jest.spyOn(Shopware.Utils.format, 'date').mockImplementation(() => 'formatted date');

        const wrapper = await createWrapper({
            extension: {
                installedAt: null,
                storeLicense: {
                    creationDate: '2025-10-21T09:28:00+00:00',
                },
            },
        });

        await flushPromises();

        expect(wrapper.get('.sw-extension-card-base__meta-info').text()).toContain('formatted date');
        expect(formatDateSpy).toHaveBeenCalledWith(expect.any(String), {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric',
            hour: undefined,
            minute: undefined,
        });
    });

    it('should not show config menu item: not active and not activated once', async () => {
        const wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: false,
                },
            },
            {
                shopwareExtensionService: {
                    canBeOpened: () => false,
                    getOpenLink: () => null,
                },
            },
        );

        await flushPromises();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state).toHaveLength(0);
    });

    it('should show config menu item: active and activated once', async () => {
        const wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: true,
                },
            },
            {
                shopwareExtensionService: {
                    getOpenLink: () => {
                        return Promise.resolve({
                            name: 'jest',
                            params: {
                                appName: 'JestApp',
                            },
                        });
                    },
                },
            },
        );
        await flushPromises();

        const state = wrapper.findAll('.sw-context-menu-item');
        expect(state).toHaveLength(1);
    });

    it('should use the extension config route as fallback open link', async () => {
        const wrapper = await createWrapper({
            extension: {
                name: 'ExampleConfigurableExtension',
                installedAt: '845618651',
                active: true,
                configurable: true,
                permissions: {},
            },
        });

        await flushPromises();

        const openExtensionItem = wrapper.findComponent({ name: 'sw-context-menu-item' });

        expect(openExtensionItem.props('routerLink')).toStrictEqual({
            name: 'sw.extension.config',
            params: {
                namespace: 'ExampleConfigurableExtension',
            },
        });
    });

    it('should not show config menu item: not active and activated once', async () => {
        const wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: false,
                },
            },
            {
                shopwareExtensionService: {
                    canBeOpened: () => true,
                    getOpenLink: () => null,
                },
            },
        );
        await flushPromises();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state).toHaveLength(0);
    });

    it('should show a consent affirmation modal if an app requires new permissions on update', async () => {
        const wrapper = await createWrapper(
            {
                extension: {
                    installedAt: '845618651',
                    permissions: [],
                },
            },
            {
                shopwareExtensionService: {
                    getOpenLink: () => null,
                    updateExtension: async () => {
                        const error = new Error();
                        error.response = {
                            data: {
                                errors: [
                                    {
                                        code: 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
                                        meta: {
                                            parameters: {
                                                deltas: ['permissions'],
                                            },
                                        },
                                    },
                                ],
                            },
                        };

                        throw error;
                    },
                },
            },
        );

        await wrapper.vm.updateExtension(false);
        expect(wrapper.get('.sw-extension-permissions-modal').exists()).toBe(true);
    });

    describe.each([
        [
            'update',
            'updateExtension',
            async (wrapper) => {
                await wrapper.get('.sw-extension-card-base__meta-info a').trigger('click');
            },
        ],
        [
            'uninstall',
            'uninstallExtension',
            async (wrapper) => {
                const uninstallItem = wrapper
                    .findAll('.sw-context-menu-item')
                    .find((item) => item.text().includes('contextMenu.uninstallLabel'));

                await uninstallItem.trigger('click');
                wrapper.findComponent('sw-extension-uninstall-modal-stub').vm.$emit('uninstall-extension', false);
            },
        ],
    ])('a failed %s', (_action, serviceMethod, startAction) => {
        it.each([
            ['FRAMEWORK__PLUGIN_COMPOSER_OPERATION_IN_PROGRESS', 'messageComposerOperationInProgress'],
            ['FRAMEWORK__PLUGIN_COMPOSER_REQUIRE', 'messageComposerRequireFailed'],
            ['FRAMEWORK__PLUGIN_COMPOSER_REMOVE', 'messageComposerRemoveFailed'],
            ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_NOT_POSSIBLE', 'messageComposerWorkingCopyNotPossible'],
            ['FRAMEWORK__PLUGIN_COMPOSER_WORKING_COPY_INTERRUPTED', 'messageComposerWorkingCopyInterrupted'],
            ['FRAMEWORK__PLUGIN_COMPOSER_DEPENDENCY_MISSING', 'messageComposerDependencyMissing'],
        ])('notifies with the own message for %s', async (code, snippetKey) => {
            const createNotificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
            const wrapper = await createWrapper(
                {
                    extension: {
                        installedAt: '845618651',
                        permissions: [],
                        allowUpdate: true,
                        version: '1.0.0',
                        latestVersion: '1.1.0',
                    },
                },
                {
                    shopwareExtensionService: {
                        getOpenLink: () => null,
                        [serviceMethod]: async () => {
                            const error = new Error();
                            error.response = {
                                data: { errors: [{ code, title: 'Internal Server Error', detail: 'Composer output' }] },
                            };

                            throw error;
                        },
                    },
                },
            );

            await startAction(wrapper);
            await flushPromises();

            expect(createNotificationSpy).toHaveBeenCalledWith(
                expect.objectContaining({ variant: 'error', message: `sw-extension.errors.${snippetKey}` }),
            );
        });
    });
});
