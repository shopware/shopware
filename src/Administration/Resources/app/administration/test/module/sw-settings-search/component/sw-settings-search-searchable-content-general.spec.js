import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-general';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-skeleton';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-searchable-content-general'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            },
            $device: {
                onResize: () => {}
            }
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    save: () => {
                        return Promise.resolve();
                    }
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
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-empty-state': true,
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-pagination': true,
            'sw-data-grid-skeleton': Shopware.Component.build('sw-data-grid-skeleton'),
            'sw-context-button': true,
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item')
        },

        propsData: {
            isEmpty: false,
            columns: [],
            repository: {},
            fieldConfigs: []
        }
    });
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content-general', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when isEmpty variable is true', async () => {
        const wrapper = createWrapper([
            'product_search_config.viewer'
        ]);

        await wrapper.setProps({
            isEmpty: true
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should call to reset ranking function when click to reset ranking action', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);
        await wrapper.vm.$nextTick();
        wrapper.vm.onResetRanking = jest.fn();

        await wrapper.setProps({
            searchConfigs: [
                {
                    apiAlias: null,
                    createdAt: '2021-01-29T02:18:11.171+00:00',
                    customFieldId: null,
                    field: 'categories.customFields',
                    id: '8bafeb17b2494781ac44dce2d3ecfae5',
                    ranking: 0,
                    searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                    searchable: false,
                    tokenize: false
                }
            ],
            isLoading: false
        });
        const firstRow = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--0'
        );

        await firstRow.find(
            '.sw-settings-search__searchable-content-list-reset'
        ).trigger('click');

        expect(wrapper.vm.onResetRanking).toHaveBeenCalled();
    });

    it('should emitted to save-config when call the reset ranking function', async () => {
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            searchConfigs: [
                {
                    apiAlias: null,
                    createdAt: '2021-01-29T02:18:11.171+00:00',
                    customFieldId: null,
                    field: 'categories.customFields',
                    id: '8bafeb17b2494781ac44dce2d3ecfae5',
                    ranking: 0,
                    searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                    searchable: false,
                    tokenize: false
                }
            ],
            isLoading: false
        });

        await wrapper.vm.onResetRanking({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5'
        });

        expect(wrapper.emitted('config-save')).toBeTruthy();
    });
});
