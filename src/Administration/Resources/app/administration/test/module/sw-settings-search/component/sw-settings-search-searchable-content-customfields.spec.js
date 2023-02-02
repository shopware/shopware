import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-skeleton';
import 'src/app/component/context-menu/sw-context-menu-item';

const customFields = mockCustomFieldData();

function mockCustomFieldData() {
    const _customFields = [];

    for (let i = 0; i < 10; i += 1) {
        const customField = {
            id: `id${i}`,
            name: `custom_additional_field_${i}`,
            config: {
                label: { 'en-GB': `Special field ${i}` },
                customFieldType: 'checkbox',
                customFieldPosition: i + 1
            }
        };

        _customFields.push(customField);
    }

    return _customFields;
}

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/custom-field',
    status: 200,
    response: {
        data: customFields
    }
});

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-searchable-content-customfields'), {
        localVue,

        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
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

describe('module/sw-settings-search/component/sw-settings-search-searchable-content-customfields', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when isEmpty variable is true', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = createWrapper();

        await wrapper.setProps({
            isEmpty: true
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('Should not able to remove item without editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onRemove = jest.fn();
        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '123456',
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

        const buttonContext = await firstRow.find(
            '.sw-settings-search__searchable-content-list-remove'
        );
        expect(buttonContext.isVisible()).toBe(true);
        expect(buttonContext.classes()).toContain('is--disabled');
    });

    it('Should able to remove item when click to remove action if having deleter privilege', async () => {
        global.activeAclRoles = ['product_search_config.deleter'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onRemove = jest.fn();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '123456',
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
            '.sw-settings-search__searchable-content-list-remove'
        ).trigger('click');

        expect(wrapper.vm.onRemove).toHaveBeenCalled();
    });

    it('Should emitted to delete-config when call the remove function if having deleter privilege', async () => {
        global.activeAclRoles = ['product_search_config.deleter'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '8bafeb17b2494781ac44dce2d3ecfae2',
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

        await wrapper.vm.onRemove({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5'
        });
        expect(wrapper.emitted('config-delete')).toBeTruthy();
    });

    it('Should call to reset ranking function when click to reset ranking action if having editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onResetRanking = jest.fn();
        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '3bafeb17b2494781ac44dce2d3ecfae4',
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

    it('Should emitted to save-config when call the reset ranking function if having the editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '23168b0c1f97454cbee670b12d045d32',
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
});
