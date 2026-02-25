/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';

describe('sw-theme-manager-list', () => {
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

    async function createWrapper({ aclCan = true, searchResult = null } = {}) {
        const component = await Shopware.Component.build('sw-theme-manager-list');
        const themes = searchResult || (() => {
            const result = [{ id: 'theme-id', salesChannels: [] }];
            result.total = 1;
            return result;
        })();

        const themeRepository = {
            search: jest.fn(() => Promise.resolve(themes)),
            save: jest.fn(() => Promise.resolve()),
        };

        return shallowMount(component, {
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-card-view': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-data-grid': true,
                    'sw-icon': true,
                    'sw-media-modal-v2': true,
                    'sw-modal': true,
                    'sw-page': true,
                    'sw-pagination': true,
                    'sw-search-bar': true,
                    'sw-skeleton': true,
                    'sw-sorting-select': true,
                    'sw-text-field': true,
                    'sw-theme-list-item': true,
                    'mt-button': true,
                    'mt-icon': true,
                    'router-link': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                    themeService: {},
                    feature: {},
                    searchRankingService: {
                        isValidTerm: () => true,
                        getSearchFieldsByEntity: () => ({}),
                    },
                },
                mocks: {
                    $t: (key) => key,
                    $route: { params: { id: 'sales-channel-id' } },
                    $router: { push: jest.fn() },
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });
    }

    it('loads theme list and updates state', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.getList();

        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.themes).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('clears loading state when list fetch fails', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.search.mockRejectedValueOnce(new Error('fail'));

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('search resets list and normalizes empty term', async () => {
        const wrapper = await createWrapper();
        const resetSpy = jest.spyOn(wrapper.vm, 'resetList').mockImplementation(() => {});

        wrapper.vm.onSearch('');

        expect(wrapper.vm.term).toBeNull();
        expect(resetSpy).toHaveBeenCalled();
    });

    it('changes sorting and resets list', async () => {
        const wrapper = await createWrapper();
        const resetSpy = jest.spyOn(wrapper.vm, 'resetList').mockImplementation(() => {});

        wrapper.vm.onSortingChanged('updatedAt:ASC');

        expect(wrapper.vm.sortBy).toBe('updatedAt');
        expect(wrapper.vm.sortDirection).toBe('ASC');
        expect(resetSpy).toHaveBeenCalled();
    });

    it('toggles list mode and updates limit', async () => {
        const wrapper = await createWrapper();
        const resetSpy = jest.spyOn(wrapper.vm, 'resetList').mockImplementation(() => {});

        wrapper.vm.listMode = 'grid';
        wrapper.vm.onListModeChange();

        expect(wrapper.vm.listMode).toBe('list');
        expect(wrapper.vm.limit).toBe(10);
        expect(resetSpy).toHaveBeenCalled();
    });

    it('opens media modal when ACL allows', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onPreviewChange(theme);

        expect(wrapper.vm.showMediaModal).toBe(true);
        expect(wrapper.vm.currentTheme).toBe(theme);
    });

    it('does not open media modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.onPreviewChange({ id: 'theme-id' });

        expect(wrapper.vm.showMediaModal).toBe(false);
        expect(wrapper.vm.currentTheme).toBeUndefined();
    });

    it('removes preview image when ACL allows', async () => {
        const wrapper = await createWrapper();
        const saveSpy = jest.spyOn(wrapper.vm, 'saveTheme').mockImplementation(() => {});
        const theme = { previewMediaId: 'media-id', previewMedia: { id: 'media-id' } };

        wrapper.vm.onPreviewImageRemove(theme);

        expect(theme.previewMediaId).toBeNull();
        expect(theme.previewMedia).toBeNull();
        expect(saveSpy).toHaveBeenCalledWith(theme);
    });

    it('skips preview image removal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });
        const theme = { previewMediaId: 'media-id', previewMedia: { id: 'media-id' } };

        wrapper.vm.onPreviewImageRemove(theme);

        expect(theme.previewMediaId).toBe('media-id');
    });

    it('updates current theme preview image', async () => {
        const wrapper = await createWrapper();
        const saveSpy = jest.spyOn(wrapper.vm, 'saveTheme').mockImplementation(() => {});
        const image = { id: 'media-id' };

        wrapper.vm.currentTheme = { previewMediaId: null, previewMedia: null };
        wrapper.vm.onPreviewImageChange([image]);

        expect(wrapper.vm.currentTheme.previewMediaId).toBe('media-id');
        expect(wrapper.vm.currentTheme.previewMedia).toBe(image);
        expect(saveSpy).toHaveBeenCalledWith(wrapper.vm.currentTheme);
    });

    it('saves theme and clears loading state on error', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.save.mockRejectedValueOnce(new Error('fail'));

        await wrapper.vm.saveTheme({ id: 'theme-id' });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('returns delete tooltip disabled when theme has no sales channels', async () => {
        const wrapper = await createWrapper();
        const tooltip = wrapper.vm.deleteDisabledToolTip({ salesChannels: [] });

        expect(tooltip.disabled).toBe(true);
    });
});
