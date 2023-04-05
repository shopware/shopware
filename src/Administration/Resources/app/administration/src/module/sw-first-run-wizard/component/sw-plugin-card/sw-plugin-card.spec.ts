import { shallowMount } from '@vue/test-utils';
import type { Wrapper } from '@vue/test-utils';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
import SwPluginCard from 'src/module/sw-first-run-wizard/component/sw-plugin-card';
import SwExtensionIcon from 'src/module/sw-extension/component/sw-extension-icon';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-button';
import 'src/app/component/utils/sw-loader';

Shopware.Component.register('sw-plugin-card', SwPluginCard);
Shopware.Component.register('sw-extension-icon', SwExtensionIcon);

async function createWrapper(plugin: unknown, showDescription: boolean): Promise<Wrapper<SwPluginCard>> {
    return shallowMount(await Shopware.Component.build('sw-plugin-card'), {
        propsData: {
            plugin,
            showDescription,
        },
        provide: {
            cacheApiService: {
                clear: () => { return Promise.resolve(); },
            },
            extensionHelperService: {
                downloadAndActivateExtension: () => { return Promise.resolve(); },
            },
        },
        stubs: {
            'sw-extension-icon': await Shopware.Component.build('sw-extension-icon'),
            'sw-icon': true,
            'sw-button-process': await Shopware.Component.build('sw-button-process'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
        },
    });
}

describe('src/module/sw-first-run-wizard/component/sw-plugin-card', () => {
    it('displays correct icon and basic information', async () => {
        const pluginConfig = {
            iconPath: 'path/to/plugin-icon',
            active: true,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'this is a example extension',
        };

        const wrapper = await createWrapper(pluginConfig, true);

        const extensionIcon = wrapper.get('.sw-extension-icon');

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
        };

        const wrapper = await createWrapper(pluginConfig, true);

        const truncatedDescription = wrapper.get('.sw-plugin-card__short-description').text();

        expect(truncatedDescription).toHaveLength(140);
        expect(truncatedDescription.endsWith('...')).toBe(true);
        expect(truncatedDescription.slice(0, 137)).toEqual(shortDescription.slice(0, 137));
    });

    it('displays that an extension is already installed', async () => {
        const wrapper = await createWrapper({
            iconPath: 'path/to/plugin-icon',
            active: true,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'short description',
        }, true);

        const isInstalled = wrapper.get('.plugin-installed');

        expect(isInstalled.get('sw-icon-stub').attributes('name')).toBe('regular-check-circle-s');
        expect(isInstalled.text()).toBe('sw-first-run-wizard.general.pluginInstalled');
    });

    it('can install an extension', async () => {
        const wrapper = await createWrapper({
            name: 'SwExamplePlugin',
            iconPath: 'path/to/plugin-icon',
            active: false,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'short description',
        }, true);

        const downloadSpy = jest.spyOn(wrapper.vm.extensionHelperService, 'downloadAndActivateExtension');
        const cacheApiSpy = jest.spyOn(wrapper.vm.cacheApiService, 'clear');

        await wrapper.get('.sw-button-process').trigger('click');

        expect(downloadSpy).toHaveBeenCalled();
        expect(downloadSpy).toHaveBeenCalledWith('SwExamplePlugin');
        expect(cacheApiSpy).toHaveBeenCalled();

        expect(wrapper.emitted('onPluginInstalled')).toEqual([['SwExamplePlugin']]);
    });

    it('displays errors on failed installation', async () => {
        const wrapper = await createWrapper({
            name: 'SwExamplePlugin',
            iconPath: 'path/to/plugin-icon',
            active: false,
            label: 'example extension',
            manufacturer: 'shopware AG',
            shortDescription: 'short description',
        }, true);

        const downloadError = new Error('installation error');

        const downloadSpy = jest.spyOn(wrapper.vm.extensionHelperService, 'downloadAndActivateExtension');
        downloadSpy.mockImplementationOnce(() => { return Promise.reject(downloadError); });

        const showExtensionErrorsSpy = jest.spyOn(wrapper.vm, 'showExtensionErrors');
        showExtensionErrorsSpy.mockImplementationOnce(() => {});

        const cacheApiSpy = jest.spyOn(wrapper.vm.cacheApiService, 'clear');

        await wrapper.get('.sw-button-process').trigger('click');

        expect(downloadSpy).toHaveBeenCalled();
        expect(downloadSpy).toHaveBeenCalledWith('SwExamplePlugin');
        expect(cacheApiSpy).toHaveBeenCalled();
        expect(showExtensionErrorsSpy).toHaveBeenCalled();
        expect(showExtensionErrorsSpy).toHaveBeenCalledWith(downloadError);

        expect(wrapper.emitted('onPluginInstalled')).toEqual([['SwExamplePlugin']]);
    });
});
