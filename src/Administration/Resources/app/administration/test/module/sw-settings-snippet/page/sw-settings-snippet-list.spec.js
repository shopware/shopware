import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import 'src/module/sw-settings-snippet/page/sw-settings-snippet-list';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';

function getSnippets() {
    const data = {
        data: {
            'account.addressCreateBtn': [
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'Neue Adresse hinzufügen',
                    resetTo: 'Neue Adresse hinzufügen',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Neue Adresse hinzufügen'
                },
                {
                    author: 'Shopware',
                    id: null,
                    origin: 'Add address',
                    resetTo: 'Add address',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Add address'
                }
            ]
        }
    };

    const totalAmountOfSnippets = Object.keys(data.data).length;
    data.total = totalAmountOfSnippets;

    return data;
}

function getSnippetSets() {
    const data = [
        {
            baseFile: 'messages.de-DE',
            id: 'a2f95068665e4498ae98a2318a7963df',
            iso: 'de-DE',
            name: 'BASE de-DE'
        }
    ];

    data.total = data.length;

    return data;
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-list', () => {
    function createWrapper(privileges = []) {
        const localVue = createLocalVue();
        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-settings-snippet-list'), {
            localVue,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(getSnippetSets()),
                        create: () => Promise.resolve(),
                        save: () => Promise.resolve(),
                    })
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    }
                },
                userService: {
                    getUser: () => Promise.resolve()
                },
                snippetSetService: {
                    getAuthors: () => {
                        return Promise.resolve({
                            data: [
                                'Shopware',
                                'System',
                            ],
                        });
                    },
                    getCustomList: () => {
                        return Promise.resolve(getSnippets());
                    }
                },
                snippetService: {
                    save: () => Promise.resolve(),
                    delete: () => Promise.resolve(),
                    getFilter: () => Promise.resolve({
                        data: [
                            'product',
                            'order',
                            'customer',
                        ],
                    }),
                },
                searchRankingService: {},
                userConfigService: {
                    search: () => {
                        return Promise.resolve({
                            data: {
                                Shopware: true,
                                System: true,
                            },
                        });
                    },
                    upsert: () => {
                        return Promise.resolve();
                    },
                },
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'test'
                        }
                    },
                    query: {
                        ids: 'a2f95068665e4498ae98a2318a7963df'
                    }
                }
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <div class="smart-bar__actions">
                            <slot name="smart-bar-actions"></slot>
                        </div>
                        <slot name="content"></slot>
                    </div>`
                },
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-pagination': true,
                'sw-data-grid-skeleton': true,
                'sw-icon': true,
                'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
                'sw-context-menu': Shopware.Component.build('sw-context-menu'),
                'sw-context-button': Shopware.Component.build('sw-context-button'),
                'sw-data-grid-settings': true,
                'router-link': true,
                'sw-popover': true,
                'sw-button': true
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        [true, 'snippet.viewer'],
        [true, 'snippet.viewer, snippet.editor'],
        [true, 'snippet.viewer, snippet.editor, snippet.creator'],
        [false, 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a reset button with an disabled state of %p with the roles: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = createWrapper(roles);

        await wrapper.vm.$nextTick();

        const contextMenuButton = wrapper.find('.sw-data-grid__row--0 .sw-context-button__button');
        contextMenuButton.trigger('click');

        await wrapper.vm.$nextTick();

        const resetButton = wrapper.find('.sw-settings-snippet-list__delete-action');

        if (!state) {
            expect(resetButton.classes()).not.toContain('is--disabled');

            return;
        }

        expect(resetButton.classes()).toContain('is--disabled');
    });

    it.each([
        ['true', 'snippet.viewer'],
        ['true', 'snippet.viewer, snippet.editor'],
        [undefined, 'snippet.viewer, snippet.editor, snippet.creator'],
        ['true', 'snippet.viewer, snippet.editor, snippet.deleter']
    ])('should have a disabled state of %p on the new snippet button when using role: %s', async (state, role) => {
        const roles = role.split(', ');

        const wrapper = createWrapper(roles);
        wrapper.setData({ isLoading: false });

        await wrapper.vm.$nextTick();

        const createSnippetButton = wrapper.find('.smart-bar__actions sw-button-stub');

        expect(createSnippetButton.attributes('disabled')).toBe(state);
    });

    it('should contain the correct data variables and computed properties', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.entityName).toEqual('snippet');
        expect(wrapper.vm.filterSettings).toEqual(null);
        expect(wrapper.vm.queryIds).toEqual(['a2f95068665e4498ae98a2318a7963df']);
    });

    it('should get filter settings when component created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getFilterSettings = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getFilterSettings).toHaveBeenCalled();
        wrapper.vm.getFilterSettings.mockRestore();
    });

    it('should add event listeners when component created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.addEventListeners = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.addEventListeners).toHaveBeenCalled();
        wrapper.vm.addEventListeners.mockRestore();
    });

    it('should save user config before destroy component', () => {
        const wrapper = createWrapper();
        wrapper.vm.saveUserConfig = jest.fn();
        wrapper.vm.removeEventListeners = jest.fn();

        wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.saveUserConfig).toHaveBeenCalled();
        expect(wrapper.vm.removeEventListeners).toHaveBeenCalled();

        wrapper.vm.saveUserConfig.mockRestore();
        wrapper.vm.removeEventListeners.mockRestore();
    });

    it('should be able to get filter settings', async () => {
        const wrapper = createWrapper();
        const getUserConfigSpy = jest.spyOn(wrapper.vm, 'getUserConfig');

        wrapper.vm.createFilterSettings = jest.fn();
        wrapper.vm.userConfigService.search = jest.fn(() => Promise.resolve({
            data: {
                'grid.filter.setting-snippet-list': {
                    Shopware: true,
                    System: false,
                },
            },
        }));

        await wrapper.vm.getFilterSettings();

        expect(getUserConfigSpy).toHaveBeenCalled();
        expect(wrapper.vm.userConfigService.search).toHaveBeenCalledWith([
            'grid.filter.setting-snippet-list',
        ]);
        expect(wrapper.vm.filterSettings).toEqual(expect.objectContaining({
            Shopware: true,
            System: false,
        }));
        expect(wrapper.vm.createFilterSettings).not.toHaveBeenCalled();
        expect(wrapper.vm.hasActiveFilters).toBe(true);

        getUserConfigSpy.mockClear();
        wrapper.vm.createFilterSettings.mockRestore();
        wrapper.vm.userConfigService.search.mockRestore();
    });

    it('should create filter settings when get filter settings', async () => {
        const wrapper = createWrapper();
        const getUserConfigSpy = jest.spyOn(wrapper.vm, 'getUserConfig');
        const createFilterSettingsSpy = jest.spyOn(wrapper.vm, 'createFilterSettings');
        wrapper.vm.userConfigService.search = jest.fn(() => Promise.resolve({ data: [] }));

        await wrapper.vm.getFilterSettings();

        expect(getUserConfigSpy).toHaveBeenCalled();
        expect(createFilterSettingsSpy).toHaveBeenCalled();
        expect(wrapper.vm.filterSettings).toEqual(expect.objectContaining({
            addedSnippets: false,
            customer: false,
            editedSnippets: false,
            emptySnippets: false,
            order: false,
            product: false,
        }));

        getUserConfigSpy.mockClear();
        createFilterSettingsSpy.mockClear();
        wrapper.vm.userConfigService.search.mockRestore();
    });

    it('should be able to save user config', () => {
        const wrapper = createWrapper();
        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalledWith({
            'grid.filter.setting-snippet-list': null,
        });
        wrapper.vm.userConfigService.upsert.mockRestore();
    });

    it('should get list correctly', async () => {
        const wrapper = createWrapper();
        wrapper.vm.initializeSnippetSet = jest.fn();

        await wrapper.setData({
            authorFilters: [
                'Shopware',
                'System',
            ],
            filterSettings: {
                Shopware: true,
                System: false,
            },
        });
        wrapper.vm.getList();

        expect(wrapper.vm.initializeSnippetSet).toHaveBeenCalledWith(
            expect.objectContaining({
                author: ['Shopware'],
            }),
        );

        wrapper.vm.initializeSnippetSet.mockRestore();
    });

    it('should be able to reset all filters', async () => {
        const wrapper = createWrapper();
        wrapper.vm.initializeSnippetSet = jest.fn();

        await wrapper.setData({
            filterSettings: {
                Shopware: true,
                System: true,
            },
        });
        wrapper.vm.onResetAll();

        expect(wrapper.vm.showOnlyEdited).toEqual(false);
        expect(wrapper.vm.showOnlyAdded).toEqual(false);
        expect(wrapper.vm.emptySnippets).toEqual(false);
        expect(wrapper.vm.appliedFilter).toEqual([]);
        expect(wrapper.vm.appliedAuthors).toEqual([]);
        Object.keys(wrapper.vm.filterSettings).forEach((key) => {
            expect(wrapper.vm.filterSettings[key]).toEqual(false);
        });
        expect(wrapper.vm.initializeSnippetSet).toHaveBeenCalledWith({});

        wrapper.vm.initializeSnippetSet.mockRestore();
    });

    it('should contain a computed property, called: activeFilters', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            filterSettings: null,
        });
        expect(wrapper.vm.hasActiveFilters).toEqual(false);
        expect(wrapper.vm.activeFilters).toEqual({});

        wrapper.setData({
            filterSettings: {
                editedSnippets: true,
                addedSnippets: true,
                emptySnippets: true,
                Shopware: true,
                System: true,
                order: true,
                product: true,
                customer: true,
            },
            filterItems: [
                'order',
                'product',
                'customer',
            ],
        });

        expect(wrapper.vm.hasActiveFilters).toEqual(true);
        expect(wrapper.vm.activeFilters).toEqual(
            expect.objectContaining({
                added: true,
                edited: true,
                empty: true,
                namespace: [
                    'order',
                    'product',
                    'customer',
                ],
            }),
        );
    });

    it('should be able to change filter settings value', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            filterSettings: {
                Shopware: false,
            },
        });
        wrapper.vm.onChange({
            name: 'Shopware',
            value: true,
        });

        expect(wrapper.vm.filterSettings.Shopware).toEqual(true);
    });

    it('should save user config before window unloads', () => {
        const wrapper = createWrapper();
        wrapper.vm.saveUserConfig = jest.fn();

        wrapper.vm.beforeUnloadListener();

        expect(wrapper.vm.saveUserConfig).toHaveBeenCalled();
        wrapper.vm.saveUserConfig.mockRestore();
    });
});
