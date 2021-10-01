import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-property/page/sw-property-list';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-property-list'), {
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
                    search: () => {
                        return Promise.resolve([
                            {
                                id: '1a2b3c4e',
                                name: 'Test property',
                                sourceEntitiy: 'property'
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            searchRankingService: {
                getSearchFieldsByEntity: () => {
                    return {
                        name: searchRankingPoint.HIGH_SEARCH_RANKING
                    };
                },
                buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                    return criteria;
                }
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-language-switch': true,
            'sw-empty-state': true,
            'sw-context-menu-item': true
        }
    });
}

describe('module/sw-property/page/sw-property-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new property', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-property-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new property', async () => {
        const wrapper = createWrapper([
            'property.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-property-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-property-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper([
            'property.editor'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-property-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-property-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'property.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-property-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-property-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'property.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-property-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should get search ranking fields as a computed field', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];

        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.searchRankingFields).toEqual({ name: searchRankingPoint.HIGH_SEARCH_RANKING });
    });

    it('should add query score to the criteria ', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];

        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria();
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
    });
});

