/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import './index';

describe('sw-theme-modal', () => {
    async function createWrapper({ repositorySearch = null, selectedThemeId = null } = {}) {
        const component = await Shopware.Component.build('sw-theme-modal');
        const themeRepository = {
            search: repositorySearch || jest.fn(() => Promise.resolve({ total: 0, length: 0 })),
        };

        return shallowMount(component, {
            props: {
                selectedThemeId,
            },
            global: {
                stubs: {
                    'sw-modal': true,
                    'sw-card-section': true,
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-theme-list-item': true,
                    'sw-button': true,
                    'mt-checkbox': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    feature: {},
                    searchRankingService: {
                        getSearchFieldsByEntity: () => ({}),
                    },
                },
                mocks: {
                    $t: (key) => key,
                    $route: { name: 'sw.theme.manager.index', query: {} },
                },
            },
        });
    }

    it('sets selected theme on created', async () => {
        const wrapper = await createWrapper({ selectedThemeId: 'theme-id' });

        expect(wrapper.vm.selected).toBe('theme-id');
    });

    it('loads theme list and updates state', async () => {
        const searchSpy = jest.fn(() => Promise.resolve({ total: 2, length: 2 }));
        const wrapper = await createWrapper({ repositorySearch: searchSpy });

        const result = await wrapper.vm.getList();

        expect(searchSpy).toHaveBeenCalledWith(expect.any(Object), Shopware.Context.api);
        expect(result).toEqual({ total: 2, length: 2 });
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.themes).toEqual({ total: 2, length: 2 });
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('clears loading state when list fetch fails', async () => {
        const wrapper = await createWrapper({
            repositorySearch: jest.fn(() => Promise.reject(new Error('fail'))),
        });

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('selects and emits modal theme selection', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.selected = 'theme-id';
        wrapper.vm.selectLayout();

        expect(wrapper.emitted('modal-theme-select')[0]).toEqual(['theme-id']);
        expect(wrapper.emitted('modal-close')).toBeDefined();
        expect(wrapper.vm.selected).toBeNull();
        expect(wrapper.vm.term).toBeNull();
    });

    it('updates selected item', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.selectItem('theme-id');

        expect(wrapper.vm.selected).toBe('theme-id');
    });

    it('search resets page and triggers list loading', async () => {
        const wrapper = await createWrapper();
        const getListSpy = jest.spyOn(wrapper.vm, 'getList').mockImplementation(() => {});

        wrapper.vm.page = 3;
        wrapper.vm.onSearch('Foo');

        expect(wrapper.vm.term).toBe('Foo');
        expect(wrapper.vm.page).toBe(1);
        expect(getListSpy).toHaveBeenCalled();
    });
});
