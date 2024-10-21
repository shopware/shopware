import { mount } from '@vue/test-utils';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
import SwExtensionIcon from 'src/app/asyncComponent/extension/sw-extension-icon';

Shopware.Component.register('sw-extension-icon', SwExtensionIcon);

async function createWrapper(plugin, showDescription) {
    return mount(await wrapTestComponent('sw-plugin-card', { sync: true }), {
        propsData: {
            plugin,
            showDescription,
        },
        global: {
            provide: {
                cacheApiService: {
                    clear: () => {
                        return Promise.resolve();
                    },
                },
                extensionHelperService: {
                    downloadAndActivateExtension: jest.fn().mockResolvedValue(),
                },
                shopwareExtensionService: {
                    updateExtensionData: () => {
                        return Promise.resolve();
                    },
                },
            },
            stubs: {
                'sw-extension-icon': await Shopware.Component.build('sw-extension-icon'),
                'sw-icon': true,
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-loader': await wrapTestComponent('sw-loader'),
                'router-link': true,
                'sw-loader-deprecated': true,
            },
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-first-run-wizard/component/sw-plugin-card', () => {
    it('displays correct icon and basic information', async () => {
        const pluginConfig = {
            iconPath: 'path/to/plugin-icon',
            active: true,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'this is a example extension',
            type: 'plugin',
        };

        const wrapper = await createWrapper(pluginConfig, true);
        await flushPromises();

        const extensionIcon = wrapper.getComponent('.sw-extension-icon');

        expect(extensionIcon.vm).toBeDefined();
        expect(extensionIcon.props('src')).toBe(pluginConfig.iconPath);

        expect(wrapper.get('.sw-plugin-card__label').text()).toBe(pluginConfig.label);
        expect(wrapper.get('.sw-plugin-card__manufacturer').text()).toBe(pluginConfig.manufacturer);
        expect(wrapper.get('.sw-plugin-card__short-description').text()).toBe(pluginConfig.shortDescription);
    });

    it('hides description', async () => {
        const pluginConfig = {
            iconPath: 'path/to/plugin-icon',
            active: true,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'this is a example extension',
            type: 'plugin',
        };

        const wrapper = await createWrapper(pluginConfig, false);

        expect(wrapper.find('.sw-plugin-card__short-description').exists()).toBe(false);
    });

    it('truncates short description correctly', async () => {
        const shortDescription = Array.from({ length: 50 }, () => 'a').join(', ');
        expect(shortDescription.length).toBeGreaterThan(140);

        const pluginConfig = {
            iconPath: 'path/to/plugin-icon',
            active: true,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription,
            type: 'plugin',
        };

        const wrapper = await createWrapper(pluginConfig, true);

        const truncatedDescription = wrapper.get('.sw-plugin-card__short-description').text();

        expect(truncatedDescription).toHaveLength(140);
        expect(truncatedDescription.endsWith('...')).toBe(true);
        expect(truncatedDescription.slice(0, 137)).toEqual(shortDescription.slice(0, 137));
    });

    it('displays that an extension is already installed', async () => {
        const wrapper = await createWrapper(
            {
                iconPath: 'path/to/plugin-icon',
                active: true,
                label: 'example extension',
                manufacturer: 'shopware AG',
                shortDescription: 'short description',
                type: 'plugin',
            },
            true,
        );

        const isInstalled = wrapper.get('.plugin-installed');

        expect(isInstalled.get('sw-icon-stub').attributes('name')).toBe('regular-check-circle-s');
        expect(isInstalled.text()).toBe('sw-first-run-wizard.general.pluginInstalled');
    });

    it('can install a plugin', async () => {
        const wrapper = await createWrapper(
            {
                name: 'SwExamplePlugin',
                iconPath: 'path/to/plugin-icon',
                active: false,
                label: 'example extension',
                manufacturer: 'shopware AG',
                shortDescription: 'short description',
                type: 'plugin',
            },
            true,
        );

        const downloadSpy = jest.spyOn(wrapper.vm.extensionHelperService, 'downloadAndActivateExtension');
        const cacheApiSpy = jest.spyOn(wrapper.vm.cacheApiService, 'clear');
        const extensionServiceSpy = jest.spyOn(wrapper.vm.shopwareExtensionService, 'updateExtensionData');

        await wrapper.get('.sw-button-process').trigger('click');
        await flushPromises();

        expect(downloadSpy).toHaveBeenCalled();
        expect(downloadSpy).toHaveBeenCalledWith('SwExamplePlugin', 'plugin');
        expect(cacheApiSpy).toHaveBeenCalled();
        expect(extensionServiceSpy).toHaveBeenCalled();
        expect(wrapper.emitted('on-plugin-installed')).toEqual([
            ['SwExamplePlugin'],
        ]);
    });

    it('can install an app', async () => {
        const wrapper = await createWrapper(
            {
                name: 'SwExampleApp',
                iconPath: 'path/to/plugin-icon',
                active: false,
                label: 'example extension',
                manufacturer: 'shopware AG',
                shortDescription: 'short description',
                type: 'app',
            },
            true,
        );

        const downloadSpy = jest.spyOn(wrapper.vm.extensionHelperService, 'downloadAndActivateExtension');
        const cacheApiSpy = jest.spyOn(wrapper.vm.cacheApiService, 'clear');
        const extensionServiceSpy = jest.spyOn(wrapper.vm.shopwareExtensionService, 'updateExtensionData');

        await wrapper.get('.sw-button-process').trigger('click');
        await flushPromises();

        expect(downloadSpy).toHaveBeenCalled();
        expect(downloadSpy).toHaveBeenCalledWith('SwExampleApp', 'app');
        expect(cacheApiSpy).not.toHaveBeenCalled();
        expect(extensionServiceSpy).toHaveBeenCalled();

        expect(wrapper.emitted('on-plugin-installed')).toEqual([
            ['SwExampleApp'],
        ]);
    });

    it('displays errors on failed installation', async () => {
        const wrapper = await createWrapper(
            {
                name: 'SwExamplePlugin',
                iconPath: 'path/to/plugin-icon',
                active: false,
                label: 'example extension',
                manufacturer: 'shopware AG',
                shortDescription: 'short description',
                type: 'plugin',
            },
            true,
        );

        const downloadError = new Error('installation error');

        const downloadSpy = jest.spyOn(wrapper.vm.extensionHelperService, 'downloadAndActivateExtension');
        downloadSpy.mockImplementationOnce(() => {
            return Promise.reject(downloadError);
        });

        const showExtensionErrorsSpy = jest.spyOn(wrapper.vm, 'showExtensionErrors');
        showExtensionErrorsSpy.mockImplementationOnce(() => {});

        const cacheApiSpy = jest.spyOn(wrapper.vm.cacheApiService, 'clear');

        const extensionServiceSpy = jest.spyOn(wrapper.vm.shopwareExtensionService, 'updateExtensionData');

        await wrapper.get('.sw-button-process').trigger('click');
        await flushPromises();

        expect(downloadSpy).toHaveBeenCalled();
        expect(downloadSpy).toHaveBeenCalledWith('SwExamplePlugin', 'plugin');
        expect(cacheApiSpy).toHaveBeenCalled();
        expect(showExtensionErrorsSpy).toHaveBeenCalled();
        expect(showExtensionErrorsSpy).toHaveBeenCalledWith(downloadError);
        expect(extensionServiceSpy).toHaveBeenCalled();

        expect(wrapper.emitted('on-plugin-installed')).toEqual([
            ['SwExamplePlugin'],
        ]);
    });
});
