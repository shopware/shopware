/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import swSettingsStorefrontIndex from './index';

Shopware.Component.register('sw-settings-storefront-index', swSettingsStorefrontIndex);

describe('sw-settings-storefront-index', () => {
    async function createWrapper({ getValues = null, saveValues = null } = {}) {
        const component = await Shopware.Component.build('sw-settings-storefront-index');
        component.methods.createdComponent = jest.fn();

        return shallowMount(component, {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                            <div>
                                <slot name="smart-bar-actions"></slot>
                            </div>
                        `,
                    },
                    'sw-button-process': {
                        props: ['processSuccess'],
                        emits: ['click', 'update:processSuccess'],
                        template: `
                            <button class="sw-button-process" @click="$emit('click')"><slot></slot></button>
                        `,
                    },
                    'sw-card-view': true,
                    'sw-skeleton': true,
                    'mt-card': true,
                    'mt-switch': true,
                    'sw-inherit-wrapper': true,
                    'sw-sales-channel-switch': true,
                    'mt-icon': true,
                },
                provide: {
                    systemConfigApiService: {
                        getValues: getValues || jest.fn(() => Promise.resolve({})),
                        saveValues: saveValues || jest.fn(() => Promise.resolve()),
                    },
                },
                mocks: {
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });
    }

    it('loads default storefront settings when config is empty', async () => {
        const getValues = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper({ getValues });

        await wrapper.vm.loadPageContent();

        expect(wrapper.vm.storefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.asyncThemeCompilation': false,
            'core.storefrontSettings.speculationRules': false,
        });
        expect(wrapper.vm.currentSalesChannelStorefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.asyncThemeCompilation': false,
            'core.storefrontSettings.speculationRules': false,
        });
        expect(getValues).toHaveBeenCalledWith('core.storefrontSettings');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('loads stored default settings', async () => {
        const stored = {
            'core.storefrontSettings.iconCache': false,
            'core.storefrontSettings.asyncThemeCompilation': true,
            'core.storefrontSettings.speculationRules': true,
        };
        const wrapper = await createWrapper({
            getValues: jest.fn(() => Promise.resolve(stored)),
        });

        await wrapper.vm.loadPageContent();

        expect(wrapper.vm.storefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': false,
            'core.storefrontSettings.asyncThemeCompilation': true,
            'core.storefrontSettings.speculationRules': true,
        });
        expect(wrapper.vm.currentSalesChannelStorefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': false,
            'core.storefrontSettings.asyncThemeCompilation': true,
            'core.storefrontSettings.speculationRules': true,
        });
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('loads selected sales channel settings as inheritable values', async () => {
        const getValues = jest.fn((_domain, salesChannelId = null) => {
            if (salesChannelId === 'sales-channel-id') {
                return Promise.resolve({
                    'core.storefrontSettings.iconCache': false,
                });
            }

            return Promise.resolve({});
        });
        const wrapper = await createWrapper({ getValues });

        await wrapper.vm.loadPageContent();
        await wrapper.vm.onSalesChannelChanged('sales-channel-id');

        expect(wrapper.vm.selectedSalesChannelId).toBe('sales-channel-id');
        expect(wrapper.vm.isGlobalConfig).toBe(false);
        expect(wrapper.vm.currentSalesChannelStorefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': false,
            'core.storefrontSettings.speculationRules': null,
        });
    });

    it('normalizes empty values before saving default scoped and global settings', async () => {
        const saveValues = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ saveValues });

        wrapper.vm.storefrontSettings = {
            'core.storefrontSettings.iconCache': '',
            'core.storefrontSettings.asyncThemeCompilation': '',
            'core.storefrontSettings.speculationRules': '',
        };

        await wrapper.vm.saveStorefrontSettings();

        expect(saveValues).toHaveBeenCalledWith({
            'core.storefrontSettings.asyncThemeCompilation': false,
        });
        expect(saveValues).toHaveBeenCalledWith({
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.speculationRules': false,
        }, null);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed with `loadstorefrontSettings`.
     */
    it('keeps loadPageContent delegated to the deprecated loadstorefrontSettings method', async () => {
        const wrapper = await createWrapper();
        const loadSpy = jest.spyOn(wrapper.vm, 'loadstorefrontSettings').mockImplementation(() => Promise.resolve());

        await wrapper.vm.loadPageContent();

        expect(loadSpy).toHaveBeenCalled();
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed with `savestorefrontSettings`.
     */
    it('keeps saveStorefrontSettings delegated to the deprecated savestorefrontSettings method', async () => {
        const wrapper = await createWrapper();
        const saveSpy = jest.spyOn(wrapper.vm, 'savestorefrontSettings').mockImplementation(() => Promise.resolve());

        await wrapper.vm.saveStorefrontSettings();

        expect(saveSpy).toHaveBeenCalled();
    });

    it('keeps inheritance values when saving selected sales channel settings', async () => {
        const saveValues = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ saveValues });

        wrapper.vm.selectedSalesChannelId = 'sales-channel-id';
        wrapper.vm.storefrontSettings = {
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.asyncThemeCompilation': true,
            'core.storefrontSettings.speculationRules': false,
        };
        wrapper.vm.salesChannelStorefrontSettings = {
            'core.storefrontSettings.iconCache': null,
            'core.storefrontSettings.speculationRules': '',
        };

        await wrapper.vm.saveStorefrontSettings();

        expect(saveValues).toHaveBeenCalledWith({
            'core.storefrontSettings.asyncThemeCompilation': true,
        });
        expect(saveValues).toHaveBeenCalledWith({
            'core.storefrontSettings.iconCache': null,
            'core.storefrontSettings.speculationRules': null,
        }, 'sales-channel-id');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('resets the save button after the success animation without reloading the page', async () => {
        const wrapper = await createWrapper();
        const loadSpy = jest.spyOn(wrapper.vm, 'loadPageContent').mockImplementation(() => Promise.resolve());

        wrapper.vm.isSaveSuccessful = true;

        wrapper.getComponent('.sw-button-process').vm.$emit('update:processSuccess', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(loadSpy).not.toHaveBeenCalled();
    });
});
