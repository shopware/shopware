/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSearchSearchableContentGeneral from 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-general';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-skeleton';

// Turn off known errors
import { missingGetListMethod } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors.push(missingGetListMethod);

Shopware.Component.register('sw-settings-search-searchable-content-general', swSettingsSearchSearchableContentGeneral);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-search-searchable-content-general'), {
        localVue,

        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
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
            searchRankingService: {}

        },

        stubs: {
            'sw-empty-state': true,
            'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-pagination': true,
            'sw-data-grid-skeleton': await Shopware.Component.build('sw-data-grid-skeleton'),
            'sw-context-button': true,
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item')
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
    beforeEach(async () => {
        global.activeAclRoles = [];

        // TODO: Remove this when the test is fixed
        global.allowedErrors = [
            {
                method: 'warn',
                msg: '[Listing Mixin]',
            }
        ];
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when isEmpty variable is true', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            isEmpty: true
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should call to reset ranking function when click to reset ranking action', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onResetRanking = jest.fn();

        const searchConfigs = [
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
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
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
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        const searchConfigs = [
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
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false
        });

        await wrapper.vm.onResetRanking({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5'
        });

        expect(wrapper.emitted('config-save')).toBeTruthy();
    });

    it('should get ref attribute of searchable list when call onInlineEditItem function', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData({
            $refs: {
                swSettingsSearchableContentGrid: {
                    onDbClickCell: () => Promise.resolve(),
                },
            },
        });
        await wrapper.vm.$nextTick();
        const searchConfigs = [
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
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false
        });

        await wrapper.vm.onInlineEditItem({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5'
        });

        expect(wrapper.vm.$refs.swSettingsSearchableContentGrid.onDbClickCell({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5'
        })).toBeTruthy();
    });
});
