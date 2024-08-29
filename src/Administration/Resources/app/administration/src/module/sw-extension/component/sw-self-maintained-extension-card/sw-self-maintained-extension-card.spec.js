import { mount } from '@vue/test-utils';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-self-maintained-extension-card', { sync: true }), {
        global: {
            stubs: {
                'sw-context-button': true,
                'sw-switch-field': true,
                'router-link': true,
                'sw-context-menu-item': true,
                'sw-loader': true,
                'sw-meteor-card': await wrapTestComponent('sw-meteor-card', { sync: true }),
                'sw-extension-icon': true,
                'sw-icon': true,
                'sw-extension-uninstall-modal': true,
                'sw-extension-removal-modal': true,
                'sw-extension-permissions-modal': true,
                'sw-extension-privacy-policy-extensions-modal': true,
                'sw-tabs': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {};
                    },
                },
                shopwareExtensionService: new ShopwareService({}, {}, {}, {}),
                cacheApiService: {
                    clear() {
                        return Promise.resolve();
                    },
                },
                extensionStoreActionService: {
                    downloadExtension: jest.fn(),
                },
            },
        },
        props: {
            extension: {
                name: 'Test',
                type: 'app',
                icon: null,
                installedAt: null,
                permissions: [],
            },
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-self-maintained-extension-card', () => {
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

    it('isInstalled should return false when not installedAt set', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.isInstalled).toBe(false);
    });

    it('isInstalled should return true when installedAt set', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                permissions: [],
            },
        });

        expect(wrapper.vm.isInstalled).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('activateExtension should install and reload the page', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: false,
                permissions: [],
            },
        });

        wrapper.vm.shopwareExtensionService.activateExtension = jest.fn(() => Promise.resolve());

        wrapper.vm.clearCacheAndReloadPage = jest.fn(() => Promise.resolve());

        await wrapper.vm.activateExtension();

        expect(wrapper.vm.shopwareExtensionService.activateExtension).toHaveBeenCalled();
        expect(wrapper.vm.clearCacheAndReloadPage).toHaveBeenCalled();
        expect(wrapper.vm.extension.active).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });


    it('deactivateExtension should install and reload the page', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.shopwareExtensionService.deactivateExtension = jest.fn(() => Promise.resolve());

        wrapper.vm.clearCacheAndReloadPage = jest.fn(() => Promise.resolve());

        await wrapper.vm.deactivateExtension();

        expect(wrapper.vm.shopwareExtensionService.deactivateExtension).toHaveBeenCalled();
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('changeExtensionStatus should call activateExtension when activated', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: true,
                permissions: [],
            },
        });

        wrapper.vm.activateExtension = jest.fn(() => Promise.resolve());

        await wrapper.vm.changeExtensionStatus();

        expect(wrapper.vm.activateExtension).toHaveBeenCalled();
    });

    it('changeExtensionStatus should call deactivateExtension when activated', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: false,
                permissions: [],
            },
        });

        wrapper.vm.deactivateExtension = jest.fn(() => Promise.resolve());

        await wrapper.vm.changeExtensionStatus();

        expect(wrapper.vm.deactivateExtension).toHaveBeenCalled();
    });
});
