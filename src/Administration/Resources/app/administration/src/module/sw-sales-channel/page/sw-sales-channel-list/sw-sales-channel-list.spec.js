/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ privileges = [], salesChannels = [] } = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        meta: {
                            $module: {
                                icon: 'regular-shopping-basket',
                            },
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(Object.assign(salesChannels, { total: salesChannels.length })),
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    domainLinkService: {
                        getDomainLink: () => null,
                    },
                    searchRankingService: {
                        isValidTerm: (term) => term && term.trim().length >= 1,
                        getSearchFieldsByEntity: () => Promise.resolve({}),
                        buildSearchQueriesForEntity: (searchFields, term, criteria) => criteria,
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                            <div class="sw-page">
                                <slot name="search-bar"></slot>
                                <slot name="smart-bar-header"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-search-bar': true,
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot /></div>',
                    },
                    'sw-entity-listing': true,
                    'sw-context-menu-item': true,
                    'sw-status': true,
                    'sw-time-ago': true,
                },
            },
        },
    );
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-list', () => {
    it('should show an empty state when no sales channels exist', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('.mt-empty-state');
        expect(emptyState.exists()).toBe(true);
        expect(emptyState.classes()).toContain('sw-sales-channel-list__empty-state');
        expect(emptyState.text()).toContain('sw-sales-channel.list.emptyStateHeadline');
        expect(emptyState.text()).toContain('sw-sales-channel.list.emptyStateDescription');
    });

    it('should show the no-result empty state instead when a search term is set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.term = 'not going to match';
        await flushPromises();

        const emptyState = wrapper.find('.mt-empty-state');
        expect(emptyState.exists()).toBe(true);
        expect(emptyState.text()).toContain('sw-empty-state.messageNoResultTitle');
        expect(emptyState.text()).not.toContain('sw-sales-channel.list.emptyStateHeadline');
    });

    it('should show the listing and no empty state when sales channels exist', async () => {
        const wrapper = await createWrapper({
            salesChannels: [
                {
                    id: '1',
                    name: 'Storefront',
                    active: true,
                    type: { iconName: 'regular-storefront' },
                    translated: { name: 'Storefront' },
                },
            ],
        });
        await flushPromises();

        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBe(true);
    });
});
