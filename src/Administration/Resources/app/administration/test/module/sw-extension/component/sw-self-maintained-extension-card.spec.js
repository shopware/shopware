import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-self-maintained-extension-card';
import 'src/module/sw-extension/component/sw-extension-card-base';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

function createWrapper() {
    Shopware.Feature.init({
        FEATURE_NEXT_12608: true
    });

    return shallowMount(Shopware.Component.build('sw-self-maintained-extension-card'), {
        propsData: {
            extension: {
                name: 'Test',
                type: 'app',
                icon: null,
                installedAt: null,
                permissions: []
            }
        },
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-context-button': true,
            'sw-switch-field': true,
            'router-link': true,
            'sw-context-menu-item': true,
            'sw-loader': true,
            'sw-meteor-card': Shopware.Component.build('sw-meteor-card')
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {};
                }
            },
            shopwareExtensionService: new ShopwareService({}, {}, {}, {}),
            cacheApiService: {
                clear() {
                    return Promise.resolve();
                }
            },
            extensionStoreActionService: {
                downloadExtension: jest.fn()
            }
        }
    });
}


describe('src/module/sw-extension/component/sw-extension-store-purchased/sw-extension-card-base', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    beforeAll(async () => {
        Shopware.Feature.init({
            FEATURE_NEXT_12608: true
        });
        await import('src/app/component/meteor/sw-meteor-card');
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('isInstalled should return false when not installedAt set', () => {
        expect(wrapper.vm.isInstalled).toBe(false);
    });

    it('isInstalled should return true when installedAt set', () => {
        wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                permissions: []
            }
        });

        expect(wrapper.vm.isInstalled).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('activateExtension should install and reload the page', async () => {
        wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: false,
                permissions: []
            }
        });

        wrapper.vm.shopwareExtensionService.activateExtension = jest.fn(() => Promise.resolve());

        wrapper.vm.clearCacheAndReloadPage = jest.fn(() => Promise.resolve());

        await wrapper.vm.activateExtension();

        expect(wrapper.vm.shopwareExtensionService.activateExtension).toBeCalled();
        expect(wrapper.vm.clearCacheAndReloadPage).toBeCalled();
        expect(wrapper.vm.extension.active).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });


    it('deactivateExtension should install and reload the page', async () => {
        wrapper.vm.shopwareExtensionService.deactivateExtension = jest.fn(() => Promise.resolve());

        wrapper.vm.clearCacheAndReloadPage = jest.fn(() => Promise.resolve());

        await wrapper.vm.deactivateExtension();

        expect(wrapper.vm.shopwareExtensionService.deactivateExtension).toBeCalled();
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('changeExtensionStatus should call activateExtension when activated', async () => {
        wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: true,
                permissions: []
            }
        });

        wrapper.vm.activateExtension = jest.fn(() => Promise.resolve());

        await wrapper.vm.changeExtensionStatus();

        expect(wrapper.vm.activateExtension).toBeCalled();
    });

    it('changeExtensionStatus should call activateExtension when activated', async () => {
        wrapper.setProps({
            extension: {
                icon: null,
                installedAt: 'a',
                active: false,
                permissions: []
            }
        });

        wrapper.vm.deactivateExtension = jest.fn(() => Promise.resolve());

        await wrapper.vm.changeExtensionStatus();

        expect(wrapper.vm.deactivateExtension).toBeCalled();
    });
});
