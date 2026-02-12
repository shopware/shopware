/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';

describe('sw-theme-manager-detail', () => {
    function ensureThemeMixinRegistered() {
        try {
            Shopware.Mixin.getByName('theme');
        } catch {
            require('../../mixin/sw-theme.mixin');
        }
    }

    beforeAll(() => {
        ensureThemeMixinRegistered();

        jest.isolateModules(() => {
            require('./index');
        });
    });

    async function createWrapper({ aclCan = true, themeServiceOverrides = {}, themeOverrides = {} } = {}) {
        const component = await Shopware.Component.build('sw-theme-manager-detail');
        component.methods.createdComponent = jest.fn();

        const themeRepository = {
            schema: { entity: 'theme' },
            get: jest.fn(() => Promise.resolve(null)),
            search: jest.fn(() => Promise.resolve({ first: () => null })),
            save: jest.fn(() => Promise.resolve()),
            getSyncChangeset: jest.fn(() => ({ changeset: [], deletions: [] })),
        };

        const defaultFolderRepository = {
            search: jest.fn(() => Promise.resolve({
                first: () => ({ folder: { id: 'default-folder-id' } }),
            })),
        };

        const salesChannelRepository = {
            search: jest.fn(() => Promise.resolve({
                getIds: () => ['sc-1'],
            })),
        };

        const themeService = {
            validateFields: jest.fn(() => Promise.resolve()),
            updateTheme: jest.fn(() => Promise.resolve()),
            assignTheme: jest.fn(() => Promise.resolve()),
            resetTheme: jest.fn(() => Promise.resolve()),
            getStructuredFields: jest.fn(() => Promise.resolve({ tabs: {}, configInheritance: [] })),
            getConfiguration: jest.fn(() => Promise.resolve({
                currentFields: {},
                fields: {},
                baseThemeFields: {},
            })),
            ...themeServiceOverrides,
        };

        return shallowMount(component, {
            data() {
                return {
                    theme: {
                        id: 'theme-id',
                        technicalName: 'MyTheme',
                        salesChannels: [],
                        configValues: {},
                        getOrigin: () => ({ salesChannels: new Map() }),
                        ...themeOverrides,
                    },
                };
            },
            global: {
                stubs: {
                    'sw-alert': true,
                    'sw-button-group': true,
                    'sw-button-process': true,
                    'sw-card': true,
                    'sw-card-section': true,
                    'sw-colorpicker': true,
                    'sw-container': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-entity-multi-select': true,
                    'sw-form-field-renderer': true,
                    'sw-icon': true,
                    'sw-inherit-wrapper': true,
                    'sw-media-modal-v2': true,
                    'sw-media-upload-v2': true,
                    'sw-modal': true,
                    'sw-page': true,
                    'sw-search-bar': true,
                    'sw-select-field': true,
                    'sw-sidebar': true,
                    'sw-sidebar-media-item': true,
                    'sw-skeleton': true,
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'sw-text-field': true,
                    'sw-upload-listener': true,
                    'sw-url-field': true,
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-text-field': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'theme') {
                                return themeRepository;
                            }
                            if (entity === 'media_default_folder') {
                                return defaultFolderRepository;
                            }
                            if (entity === 'sales_channel') {
                                return salesChannelRepository;
                            }
                            return { get: jest.fn() };
                        },
                    },
                    themeService,
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                    feature: {},
                },
                mocks: {
                    $t: (key) => key,
                    $tc: (key) => key,
                    $route: { params: { id: 'theme-id' } },
                    $router: { push: jest.fn() },
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });
    }

    beforeEach(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
    });

    it('determines derived state based on theme inheritance', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.theme = null;
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.theme = { technicalName: 'Storefront' };
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.theme = { technicalName: 'Custom', baseConfig: { configInheritance: ['@Storefront'] } };
        wrapper.vm.parentTheme = null;
        expect(wrapper.vm.isDerived).toBe(true);

        wrapper.vm.theme = { technicalName: 'Custom', baseConfig: { configInheritance: ['@Other'] } };
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.parentTheme = { id: 'parent' };
        expect(wrapper.vm.isDerived).toBe(true);
    });

    it('should keep default tab first without reordering other tabs', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.structuredThemeFields = {
            tabs: {
                layout: { labelSnippetKey: 'layout' },
                advanced: { labelSnippetKey: 'advanced' },
                default: { labelSnippetKey: 'default' },
            },
        };

        expect(Object.keys(wrapper.vm.orderedTabs)).toEqual([
            'default',
            'layout',
            'advanced',
        ]);
    });

    it('sanitizes CSS values', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.cssValue(null)).toBe('');
        expect(wrapper.vm.cssValue('`foo´bar`')).toBe('foobar');
    });

    it('builds clean changeset without invalid config entries', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.theme.configValues = { existing: 'value' };
        wrapper.vm.currentThemeConfigInitial = {
            foo: { value: 'a' },
            bar: { value: 'b' },
        };
        wrapper.vm.currentThemeConfig = {
            foo: { value: 'changed' },
            bar: { value: 'b' },
        };
        wrapper.vm.themeConfig = {
            foo: { type: 'text' },
            bar: { type: null },
        };

        const changeset = wrapper.vm.getCurrentChangeset(true);

        expect(changeset).toEqual({
            foo: { value: 'changed' },
        });
    });

    it('removes inherited values from changeset', async () => {
        const wrapper = await createWrapper();

        const { removeInheritedFromChangeset } = wrapper.vm.$options.methods;
        const context = {
            wrapperIsVisible: (key) => key === 'foo',
            $refs: {
                'wrapper-foo': [{ isInherited: true }],
            },
            inheritanceChanged: {
                'wrapper-bar': true,
            },
        };

        const changes = { foo: 'value', bar: 'value', baz: 'value' };
        removeInheritedFromChangeset.call(context, changes);

        expect(changes).toEqual({ baz: 'value' });
    });

    it('builds field bindings with component-specific config', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getSnippet = jest.fn(() => 'Translated');

        const bind = wrapper.vm.getBind({
            type: 'text',
            label: 'Label',
            custom: { componentName: 'sw-text-field' },
        });

        expect(bind).toEqual({
            type: 'text',
            config: expect.objectContaining({
                componentName: 'sw-text-field',
            }),
        });

        const selectBind = wrapper.vm.getBind({
            type: 'select',
            label: 'Label',
            custom: {
                componentName: 'sw-single-select',
                options: [{ labelSnippetKey: 'label.key', label: 'Fallback' }],
            },
        });

        expect(selectBind.config.options[0].label).toBe('Translated');
        expect(selectBind.config.custom).toBeUndefined();
        expect(selectBind.config.componentName).toBe('sw-single-select');
    });

    it('gets snippets with prefix fallback and warns when missing', async () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const wrapper = await createWrapper();
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];

        wrapper.vm.$t = (key) => {
            if (key === 'sw-theme.MyTheme.label.key') {
                return 'Translated';
            }
            return key;
        };

        expect(wrapper.vm.getSnippet('label.key', 'Fallback')).toBe('Translated');

        wrapper.vm.$t = (key) => key;
        expect(wrapper.vm.getSnippet('missing.key', 'Fallback')).toBe('Fallback');
        expect(warnSpy).toHaveBeenCalled();

        warnSpy.mockRestore();
    });

    it('returns field labels with fallback to field name', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getSnippet = jest.fn(() => 'Name');

        expect(wrapper.vm.getFieldLabel({ labelSnippetKey: 'foo', label: 'Label' }, 'field')).toBe('Name');

        wrapper.vm.getSnippet = jest.fn(() => '');
        expect(wrapper.vm.getFieldLabel({ labelSnippetKey: 'foo', label: '' }, 'field')).toBe('field');
    });

    it('returns help text from snippets or locale map', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getSnippet = jest.fn(() => 'Help text');

        expect(wrapper.vm.getHelpText({ helpTextSnippetKey: 'foo', helpText: '' })).toBe('Help text');

        wrapper.vm.getSnippet = jest.fn(() => ({ 'en-GB': 'Locale help' }));
        expect(wrapper.vm.getHelpText({ helpTextSnippetKey: 'foo', helpText: {} })).toBe('Locale help');
    });

    it('returns default tab label when snippet is empty', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getSnippet = jest.fn(() => '');
        wrapper.vm.$t = jest.fn(() => 'Default');

        expect(wrapper.vm.getTabLabel('tab.key', '')).toBe('Default');
    });

    it('disables selection when default theme and sales channel already assigned', async () => {
        const wrapper = await createWrapper({
            themeOverrides: {
                getOrigin: () => ({
                    salesChannels: new Map([['sc-1', {}]]),
                }),
            },
        });
        wrapper.vm.defaultTheme = { id: 'theme-id' };
        wrapper.vm.theme = { id: 'theme-id', getOrigin: wrapper.vm.theme.getOrigin };

        expect(wrapper.vm.selectionDisablingMethod({ id: 'sc-1' })).toBe(true);

        wrapper.vm.defaultTheme = { id: 'other' };
        expect(wrapper.vm.selectionDisablingMethod({ id: 'sc-1' })).toBe(false);
    });

    it('validates theme fields and handles invalid scss errors', async () => {
        const error = {
            response: {
                data: {
                    errors: [{
                        code: 'THEME__INVALID_SCSS_VAR',
                        detail: 'Bad var',
                    }],
                },
            },
        };
        const themeService = {
            validateFields: jest.fn(() => Promise.reject(error)),
        };
        const wrapper = await createWrapper({ themeServiceOverrides: themeService });
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getCurrentChangeset = jest.fn(() => ({ foo: 'bar' }));
        wrapper.vm.removeInheritedFromChangeset = jest.fn();

        await wrapper.vm.onValidate();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({
            title: 'sw-theme-manager.detail.validate.failed',
            autoClose: false,
        }));
    });

    it('saves theme config via API with reset and validation', async () => {
        const themeService = {
            updateTheme: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({ themeServiceOverrides: themeService });
        wrapper.vm.getCurrentChangeset = jest.fn(() => ({ foo: 'bar' }));
        wrapper.vm.removeInheritedFromChangeset = jest.fn();

        await wrapper.vm.saveThemeConfig();

        expect(themeService.updateTheme).toHaveBeenCalledWith('theme-id', { config: { foo: 'bar' } }, { reset: true, validate: true });
    });

    it('handles compiling error on save', async () => {
        const error = {
            response: {
                data: {
                    errors: [{
                        code: 'THEME__COMPILING_ERROR',
                        detail: 'Compile error',
                    }],
                },
            },
        };
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.saveSalesChannels = jest.fn(() => Promise.resolve());
        wrapper.vm.saveThemeConfig = jest.fn(() => Promise.reject(error));

        await wrapper.vm.onSaveTheme();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({
            title: 'sw-theme-manager.detail.error.themeCompile.title',
            autoClose: false,
        }));
    });

    it('handles invalid configuration errors on save', async () => {
        const error = {
            response: {
                data: {
                    errors: [{
                        code: 'THEME__INVALID_SCSS_VAR',
                        detail: 'Invalid var',
                        meta: { parameters: { name: 'config-field' } },
                    }],
                },
            },
        };
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.saveSalesChannels = jest.fn(() => Promise.resolve());
        wrapper.vm.saveThemeConfig = jest.fn(() => Promise.reject(error));

        await wrapper.vm.onSaveTheme();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({
            title: 'sw-theme-manager.detail.error.invalidConfiguration.title',
            autoClose: true,
        }));
        expect(wrapper.vm.themeConfigErrors['config-field']).toBeDefined();
    });

    it('uses first media item when changing media selection', async () => {
        const wrapper = await createWrapper();
        const addSpy = jest.spyOn(wrapper.vm, 'onAddMediaToTheme').mockImplementation(() => {});

        wrapper.vm.activeMediaField = 'field';
        wrapper.vm.currentThemeConfig = {
            field: { value: null },
        };

        wrapper.vm.onMediaChange([{ id: 'media-id' }]);

        expect(addSpy).toHaveBeenCalledWith({ id: 'media-id' }, wrapper.vm.currentThemeConfig.field);
    });
});
