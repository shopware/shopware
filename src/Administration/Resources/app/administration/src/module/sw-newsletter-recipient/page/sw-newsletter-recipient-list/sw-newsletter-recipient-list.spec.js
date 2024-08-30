/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

function mockApiCall(type) {
    switch (type) {
        case 'language' || 'languageFilters':
            return [{
                localeId: '575d2f35a8144b79beefe70e158eb03e',
                translationCodeId: '575d2f35a8144b79beefe70e158eb03e',
                name: 'Deutsch',
                createdAt: '2020-09-08T08:32:01.331+00:00',
                updatedAt: null,
                id: '25c6e7681c334d0caebae74c382c68e1',
            }];
        case 'newsletter_recipient':
            return [{
                email: 'test@example.com',
                title: null,
                firstName: 'Max',
                lastName: 'Mustermann',
                zipCode: '48624',
                city: 'Schöppingen',
                street: null,
                status: 'direct',
                hash: 'c225f2cc023946679c4e0d9189375402',
                confirmedAt: null,
                salutationId: 'fd04f0ca555143ab9f28294699f7384b',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                salesChannelId: '7b872c384b254613b5a4bd5c8b965bab',
                createdAt: '2020-09-23T11:42:12.104+00:00',
                updatedAt: '2020-09-23T13:27:01.436+00:00',
                apiAlias: null,
                id: '92618290af63445b973cc1021d60e3f5',
                salesChannel: {},
            }];

        case 'sales_channel':
            return [{
                typeId: '8a243080f92e4c719546314b577cf82b',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                customerGroupId: 'cfbd5018d38d41d8adca10d94fc8bdd6',
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                paymentMethodId: 'af6c68b88b2d473f8f029d9d84a9f356',
                shippingMethodId: '39bbd086fd47486eb1d0cf0b7cc91920',
                countryId: 'f084714d257140a38206c8a6ed11eb3a',
                navigationCategoryId: 'e66b31de54c54ad383cc00a91cc0d4c8',
                navigationCategoryVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                navigationCategoryDepth: 2,
                name: 'Storefront',
                taxCalculationType: 'horizontal',
                accessKey: 'SWSCMVRMCKY5WXLNTXRYYLVPQG',
                translated: { name: 'Storefront', customFields: [] },
                id: '7b872c384b254613b5a4bd5c8b965bab',
            }];
        default:
            throw new Error(`no data for ${type} available`);
    }
}


class MockRepositoryFactory {
    constructor(type) {
        this.data = mockApiCall(type);
    }

    search() {
        return new Promise((resolve) => {
            resolve(this.data);
        });
    }
}


async function createWrapper() {
    return mount(await wrapTestComponent('sw-newsletter-recipient-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"><slot name="grid"></slot></slot></div>',
                },
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-empty-state': {
                    template: '<div class="sw-empty-state"></div>',
                },
                'sw-entity-listing': {
                    props: ['items', 'allowView', 'allowEdit', 'allowDelete', 'allowInlineEdit'],
                    data() {
                        return {
                            isInlineEdit: false,
                        };
                    },
                    template: `
                    <div>
                    <template v-for="item in items">

                        <template slot="column-firstName" slot-scope="{ item, compact, isInlineEdit }">

                            <template v-if="isInlineEdit">
                                <sw-text-field class="sw-newsletter-recipient-list__inline-edit-first-name"
                                               v-model="item.firstName"
                                               :size="compact ? 'small' : 'default'">
                                </sw-text-field>

                                <sw-text-field class="sw-newsletter-recipient-list__inline-edit-last-name"
                                               v-model="item.lastName"
                                               :size="compact ? 'small' : 'default'">
                                </sw-text-field>
                            </template>

                            <template v-else>
                                {{ item.firstName }} {{ item.lastName }}
                            </template>
                        </template>
                        <slot name="detail-action" v-bind="{ item }">
                            <sw-context-menu-item class="sw-entity-listing__context-menu-edit-action"
                                                  :disabled="!allowEdit && !allowView">
                            </sw-context-menu-item>
                        </slot>
                        <slot name="delete-action" v-bind="{ item, allowDelete }">
                            <sw-context-menu-item class="sw-entity-listing__context-menu-edit-delete"
                                                  :disabled="!allowDelete"
                            >
                            </sw-context-menu-item>
                        </slot>
                    </template>
                    </div>`,
                },
                'sw-container': true,
                'sw-button': true,
                'sw-loader': true,
                'sw-search-bar': true,
                'sw-icon': true,
                'sw-text-field': true,
                'sw-label': true,
                'router-link': true,
                'sw-sidebar-item': true,
                'sw-newsletter-recipient-filter-switch': true,
                'sw-sidebar-collapse': true,
                'sw-entity-multi-select': true,
                'sw-sidebar': true,
            },
            provide: {
                repositoryFactory: {
                    create: (type) => new MockRepositoryFactory(type),
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
                },
            },
        },
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have no rights', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-entity-listing__context-menu-edit-delete').classes()).toContain('is--disabled');
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = [
            'newsletter_recipient.editor',
        ];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').classes()).not.toContain('is--disabled');
        expect(wrapper.find('.sw-entity-listing__context-menu-edit-delete').classes()).toContain('is--disabled');
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = [
            'newsletter_recipient.deleter',
        ];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-entity-listing__context-menu-edit-delete').classes()).not.toContain('is--disabled');
    });

    it('should be to edit and delete', async () => {
        global.activeAclRoles = [
            'newsletter_recipient.editor',
            'newsletter_recipient.deleter',
        ];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').classes()).not.toContain('is--disabled');
        expect(wrapper.find('.sw-entity-listing__context-menu-edit-delete').classes()).not.toContain('is--disabled');
    });

    it('should add query score to the criteria', async () => {
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
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('.sw-empty-state');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });
});
