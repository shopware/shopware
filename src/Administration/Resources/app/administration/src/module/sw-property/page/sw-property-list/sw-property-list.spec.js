/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-property-list', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'product') {
                            return {
                                searchIds: jest.fn(() => Promise.resolve({ data: [], total: 0 })),
                            };
                        }

                        if (entityName === 'property_group') {
                            return {
                                search: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: [
                                            {
                                                id: '1a2b3c4e',
                                                name: 'Test property',
                                                sourceEntitiy: 'property',
                                            },
                                        ],
                                    }),
                                delete: jest.fn(() => Promise.resolve()),
                            };
                        }

                        return {};
                    },
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => {
                        return Promise.resolve({
                            name: searchRankingPoint.HIGH_SEARCH_RANKING,
                        });
                    },
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                        return criteria;
                    },
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">CONTENT</slot>
                            <slot></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    props: [
                        'items',
                        'allow-inline-edit',
                    ],
                    template: `
                        <div>
                            <template v-for="item in items">
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>`,
                },
                'sw-language-switch': true,
                'sw-empty-state': true,
                'sw-context-menu-item': {
                    template: '<div class="sw-context-menu-item"><slot></slot></div>',
                    props: ['disabled'],
                },
                'router-link': true,
                'sw-checkbox-field': true,
                'sw-sidebar-item': true,
                'sw-sidebar': true,
            },
        },
    });
}

describe('module/sw-property/page/sw-property-list', () => {
    it('should not be able to create a new property', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.getComponent('.sw-property-list__button-create');

        expect(createButton.props('disabled')).toBe(true);
    });

    it('should be able to create a new property', async () => {
        global.activeAclRoles = ['property.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.getComponent('.sw-property-list__button-create');

        expect(createButton.props('disabled')).toBe(false);
    });

    it('should not be able to inline edit', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-property-list-grid');

        expect(entityListing.props('allowInlineEdit')).toBe(false);
    });

    it('should be able to inline edit', async () => {
        global.activeAclRoles = ['property.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-property-list-grid');
        expect(entityListing.props('allowInlineEdit')).toBe(true);
    });

    it('should not be able to delete', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.getComponent('.sw-property-list__delete-action');
        expect(deleteMenuItem.props('disabled')).toBe(true);
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = ['property.deleter'];

        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.getComponent('.sw-property-list__delete-action');
        expect(deleteMenuItem.props('disabled')).toBe(false);
    });

    it('should not be able to edit', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.getComponent('.sw-property-list__edit-action');
        expect(editMenuItem.props('disabled')).toBe(true);
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = ['property.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.getComponent('.sw-property-list__edit-action');
        expect(editMenuItem.props('disabled')).toBe(false);
    });

    it('should add query score to the criteria', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBe(false);
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not delete property group if it is in use', async () => {
        global.activeAclRoles = ['property.deleter'];

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productRepository.searchIds = jest.fn(() => Promise.resolve({ data: ['some-product-id'], total: 1 }));
        const notifySpy = jest.spyOn(wrapper.vm, 'createNotificationError').mockImplementation(() => {});

        await wrapper.vm.onConfirmDelete('test-group-id');

        expect(notifySpy).toHaveBeenCalledWith({ message: expect.any(String) });
        expect(wrapper.vm.propertyRepository.delete).not.toHaveBeenCalled();

        wrapper.vm.productRepository.searchIds.mockRestore();
        notifySpy.mockRestore();
    });
});
