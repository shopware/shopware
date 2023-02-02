import { shallowMount } from '@vue/test-utils';
import swExtensionCardBase from 'src/module/sw-extension/component/sw-extension-card-base';

Shopware.Component.register('sw-extension-card-base', swExtensionCardBase);

async function createWrapper(propsData = {}, provide = {}) {
    return shallowMount(await Shopware.Component.build('sw-extension-card-base'), {
        propsData: {
            extension: { installedAt: null },
            ...propsData
        },
        stubs: {
            'sw-meteor-card': true,
            'sw-switch-field': true,
            'sw-context-button': true,
            'sw-context-menu': true,
            'sw-context-menu-item': true,
            'sw-loader': true,
            'sw-extension-permissions-modal': true,
        },
        provide: {
            shopwareExtensionService: {
                getOpenLink: () => null
            },
            extensionStoreActionService: {},
            cacheApiService: {},
            ...provide
        }
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.Context.api.assetsPath = '';
        Shopware.Utils.debug.warn = () => {};
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the correct computed values', async () => {
        wrapper = await createWrapper({
            extension: {
                installedAt: null,
                permissions: []
            }
        });
    });

    it('should show the correct image (icon)', async () => {
        wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                icon: 'my-icon',
                permissions: []
            }
        });

        expect(wrapper.vm.image).toEqual('my-icon');
    });

    it('should show the correct image (iconRaw)', async () => {
        const base64Example = 'z87hufieajh38haefwa9hefjio';

        wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                iconRaw: base64Example,
                permissions: []
            }
        });

        expect(wrapper.vm.image).toEqual(`data:image/png;base64, ${base64Example}`);
    });

    it('should show the correct image (default theme asset)', async () => {
        wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                permissions: []
            }
        });

        expect(wrapper.vm.image).toEqual('administration/static/img/theme/default_theme_preview.jpg');
    });

    it('should be installed', async () => {
        wrapper = await createWrapper({
            extension: {
                installedAt: '845618651',
                permissions: []
            }
        });

        expect(wrapper.vm.isInstalled).toEqual(true);
    });

    it('should not be installed', async () => {
        wrapper = await createWrapper({
            extension: {
                installedAt: null
            }
        });

        expect(wrapper.vm.isInstalled).toEqual(false);
    });

    it('should not show config menu item: not active and not activated once', async () => {
        wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: false
                }
            },
            {
                shopwareExtensionService: {
                    canBeOpened: () => false,
                    getOpenLink: () => null
                }
            }
        );
        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state.length).toBe(0);
    });

    it('should show config menu item: active and activated once', async () => {
        wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: true
                }
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
                    }
                }
            }
        );
        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state.length).toBe(1);
    });

    it('should not show config menu item: not active and activated once', async () => {
        wrapper = await createWrapper(
            {
                extension: {
                    installedAt: null,
                    active: false
                }
            },
            {
                shopwareExtensionService: {
                    canBeOpened: () => true,
                    getOpenLink: () => null
                }
            }
        );
        await wrapper.vm.$nextTick();

        const state = wrapper.findAll('sw-context-menu-item-stub');
        expect(state.length).toBe(0);
    });

    it('should show a consent affirmation modal if an app requires new permissions on update', async () => {
        wrapper = await createWrapper(
            {
                extension: {
                    installedAt: '845618651',
                    permissions: []
                }
            },
            {
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
                                            deltas: ['permissions']
                                        }
                                    }
                                }]
                            }
                        };

                        throw error;
                    }
                }
            }
        );

        await wrapper.vm.$nextTick();

        await wrapper.vm.updateExtension(false);

        await new Promise(process.nextTick);

        wrapper.get('sw-extension-permissions-modal-stub');
    });
});
