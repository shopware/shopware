/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

// Turn off known errors
import { missingGetListMethod } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors.push(missingGetListMethod);

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-search-searchable-content-general', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,

            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        save: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                searchRankingService: {},

            },

            stubs: {
                'sw-empty-state': true,
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-pagination': true,
                'sw-data-grid-skeleton': await wrapTestComponent('sw-data-grid-skeleton'),
                'sw-context-button': true,
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
            },
        },

        props: {
            isEmpty: false,
            columns: [],
            repository: {},
            fieldConfigs: [],
        },
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
            },
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
            isEmpty: true,
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should call to reset ranking function when click to reset ranking action', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onResetRanking = jest.fn();
        await flushPromises();

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
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });
        await flushPromises();

        const firstRow = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--0',
        );

        await firstRow.find(
            '.sw-settings-search__searchable-content-list-reset',
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
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await flushPromises();

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });
        await flushPromises();

        await wrapper.vm.onResetRanking({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5',
        });

        expect(wrapper.emitted('config-save')).toBeTruthy();
    });
});
