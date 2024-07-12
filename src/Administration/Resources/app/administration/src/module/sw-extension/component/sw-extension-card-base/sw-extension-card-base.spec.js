import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}, provide = {}) {
    return mount(await wrapTestComponent('sw-extension-card-base', { sync: true }), {
        global: {
            provide: {
                shopwareExtensionService: {
                    getOpenLink: () => null,
                },
                extensionStoreActionService: {},
                cacheApiService: {},
                ...provide,
            },
        },
        props: {
            extension: { installedAt: null },
            ...propsData,
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-card-base', () => {
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

        expect(wrapper.vm.image).toBe('administration/static/img/theme/default_theme_preview.jpg');
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

    it('should not show config menu item: not active and not activated once', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: null,
                active: false,
            },
        }, {
            shopwareExtensionService: {
                canBeOpened: () => false,
                getOpenLink: () => null,
            },
        });

        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state).toHaveLength(0);
    });

    it('should show config menu item: active and activated once', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: null,
                active: true,
            },
        }, {
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
        });
        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item');
        expect(state).toHaveLength(1);
    });

    it('should not show config menu item: not active and activated once', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: null,
                active: false,
            },
        }, {
            shopwareExtensionService: {
                canBeOpened: () => true,
                getOpenLink: () => null,
            },
        });
        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state).toHaveLength(0);
    });

    it('should show a consent affirmation modal if an app requires new permissions on update', async () => {
        const wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                permissions: [],
            },
        }, {
            shopwareExtensionService: {
                getOpenLink: () => null,
                updateExtension: async () => {
                    const error = new Error();
                    error.response = {
                        data: {
                            errors: [{
                                code: 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
                                meta: {
                                    parameters: {
                                        deltas: ['permissions'],
                                    },
                                },
                            }],
                        },
                    };

                    throw error;
                },
            },
        });

        await wrapper.vm.updateExtension(false);
        expect(wrapper.get('sw-extension-permissions-modal').exists()).toBe(true);
    });
});
