/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import './index';

describe('sw-settings-storefront-index', () => {
    async function createWrapper({ getValues = null, saveValues = null } = {}) {
        const component = await Shopware.Component.build('sw-settings-storefront-index');
        component.methods.createdComponent = jest.fn();

        return shallowMount(component, {
            global: {
                stubs: {
                    'sw-page': true,
                    'sw-button-process': true,
                    'sw-card-view': true,
                    'sw-skeleton': true,
                    'sw-card': true,
                    'sw-settings-storefront-configuration': true,
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

    it('loads default settings when config is empty', async () => {
        const wrapper = await createWrapper({
            getValues: jest.fn(() => Promise.resolve({})),
        });

        await wrapper.vm.loadstorefrontSettings();

        expect(wrapper.vm.storefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.asyncThemeCompilation': false,
            'core.storefrontSettings.speculationRules': false,
        });
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('keeps stored settings when config is not empty', async () => {
        const stored = {
            'core.storefrontSettings.iconCache': false,
        };
        const wrapper = await createWrapper({
            getValues: jest.fn(() => Promise.resolve(stored)),
        });

        await wrapper.vm.loadstorefrontSettings();

        expect(wrapper.vm.storefrontSettings).toEqual(stored);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('normalizes empty values before saving', async () => {
        const saveValues = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ saveValues });

        wrapper.vm.storefrontSettings = {
            'core.storefrontSettings.iconCache': '',
            'core.storefrontSettings.asyncThemeCompilation': '',
            'core.storefrontSettings.speculationRules': '',
        };

        await wrapper.vm.savestorefrontSettings();

        expect(wrapper.vm.storefrontSettings).toEqual({
            'core.storefrontSettings.iconCache': true,
            'core.storefrontSettings.asyncThemeCompilation': false,
            'core.storefrontSettings.speculationRules': false,
        });
        expect(saveValues).toHaveBeenCalledWith(wrapper.vm.storefrontSettings);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('reloads content after save finish', async () => {
        const wrapper = await createWrapper();
        const loadSpy = jest.spyOn(wrapper.vm, 'loadPageContent').mockImplementation(() => {});

        await wrapper.vm.onSaveFinish();

        expect(loadSpy).toHaveBeenCalled();
    });
});
