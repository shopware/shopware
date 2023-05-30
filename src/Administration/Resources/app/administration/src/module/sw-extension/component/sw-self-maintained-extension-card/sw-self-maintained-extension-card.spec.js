import { shallowMount } from '@vue/test-utils';
import swSelfMaintainedExtensionCard from 'src/module/sw-extension/component/sw-self-maintained-extension-card';
import swExtensionCardBase from 'src/module/sw-extension/component/sw-extension-card-base';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/meteor/sw-meteor-card';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

Shopware.Component.register('sw-extension-card-base', swExtensionCardBase);
Shopware.Component.extend('sw-self-maintained-extension-card', 'sw-extension-card-base', swSelfMaintainedExtensionCard);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-self-maintained-extension-card'), {
        propsData: {
            extension: {
                name: 'Test',
                type: 'app',
                icon: null,
                installedAt: null,
                permissions: [],
            },
        },
        stubs: {
            'sw-context-button': true,
            'sw-switch-field': true,
            'router-link': true,
            'sw-context-menu-item': true,
            'sw-loader': true,
            'sw-meteor-card': await Shopware.Component.build('sw-meteor-card'),
            'sw-extension-icon': true,
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
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-self-maintained-extension-card', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('isInstalled should return false when not installedAt set', async () => {
        expect(wrapper.vm.isInstalled).toBe(false);
    });

    it('isInstalled should return true when installedAt set', async () => {
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
        wrapper.vm.shopwareExtensionService.deactivateExtension = jest.fn(() => Promise.resolve());

        wrapper.vm.clearCacheAndReloadPage = jest.fn(() => Promise.resolve());

        await wrapper.vm.deactivateExtension();

        expect(wrapper.vm.shopwareExtensionService.deactivateExtension).toHaveBeenCalled();
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('changeExtensionStatus should call activateExtension when activated', async () => {
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
