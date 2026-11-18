import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */

const connections = {
    products: 412,
    media: 112,
    categories: 16,
    customers: 1,
    orders: 33,
    shippingMethods: 0,
    newsletterRecipients: 0,
    landingPages: 3,
    rules: 0,
};
// Reads that make every assignment area of a tag readable
const allAreaReads = [
    'product:read',
    'property_group_option:read',
    'property_group:read',
    'media:read',
    'category:read',
    'customer:read',
    'order:read',
    'order_customer:read',
    'shipping_method:read',
    'newsletter_recipient:read',
    'landing_page:read',
    'rule:read',
];
const deleteEndpoint = jest.fn(() => Promise.resolve());
const cloneEndpoint = jest.fn(() => Promise.resolve());
const searchEndpoint = jest.fn();

async function createWrapper(privileges = [], areaReads = allAreaReads) {
    const responseMock = [
        {
            id: '1',
            name: 'ExampleTag',
        },
        {
            id: '2',
            name: 'AnotherExampleTag',
        },
    ];

    responseMock.aggregations = {};
    responseMock.total = 2;
    searchEndpoint.mockReset();
    searchEndpoint.mockResolvedValue(responseMock);

    Object.keys(connections).forEach((connection) => {
        responseMock.aggregations[connection] = {
            buckets: [
                {
                    key: '1',
                    [connection]: {
                        count: connections[connection],
                    },
                },
            ],
        };
    });

    return mount(
        await wrapTestComponent('sw-settings-tag-list', {
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
                                icon: 'regular-tag',
                            },
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: searchEndpoint,

                            delete: deleteEndpoint,

                            clone: cloneEndpoint,
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier) || areaReads.includes(identifier);
                        },
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                    tagApiService: {
                        filterIds: jest.fn(() => Promise.resolve({ total: 1, ids: ['1'] })),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-card-view': {
                        template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-card': {
                        template: `
                    <div class="mt-card">
                        <slot name="toolbar"></slot>
                        <slot name="grid"></slot>
                    </div>
                `,
                    },
                    'sw-entity-listing': {
                        props: ['items', 'dataSource', 'columns'],
                        template: `
                    <div>
                        <slot name="bulk-additional" v-bind="{ selectionCount: 2 }"></slot>
                        <template v-for="item in (dataSource || items)">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
                    },
                    'sw-context-menu-item': true,
                    'sw-search-bar': true,
                    'sw-loader': true,
                    'sw-modal': true,
                    'sw-empty-state': true,
                    'sw-card-filter': true,
                    'sw-context-menu-divider': true,

                    'sw-multi-select': {
                        props: ['options'],
                        template: '<div></div>',
                    },
                    'sw-context-button': true,

                    'sw-label': true,
                    'sw-unchecked-areas-hint': await wrapTestComponent('sw-unchecked-areas-hint', { sync: true }),
                    'sw-text-field': true,
                    'sw-settings-tag-detail-modal': true,
                },
            },
        },
    );
}

describe('module/sw-settings-tag/page/sw-settings-tag-list', () => {
    it('should be able to create a new tag', async () => {
        const wrapper = await createWrapper(['tag.creator']);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new tag', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes('disabled')).toBeDefined();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a tag', async () => {
        const wrapper = await createWrapper(['tag.editor']);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a tag', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a tag', async () => {
        const wrapper = await createWrapper(['tag.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a tag', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should return summary of total connections', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const expected = {};
        Object.entries(connections).forEach(([propertyName, count]) => {
            if (!count) {
                return;
            }

            expected[propertyName] = count;
        });
        const counts = wrapper.vm.getCounts('1');

        expect(counts).toEqual(expected);
    });

    it('should return total of single assignment', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.getPropertyCounting('products', '1')).toBe(412);
        expect(wrapper.vm.getPropertyCounting('invalid', '1')).toBe(0);
        expect(wrapper.vm.getPropertyCounting('products', 'invalid')).toBe(0);
    });

    it('should use tag api service for duplicate filter', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            sortBy: 'products',
        });
        await flushPromises();

        expect(wrapper.vm.total).toBe(2);

        await wrapper.setData({
            duplicateFilter: true,
        });

        wrapper.vm.onFilter();
        await flushPromises();

        expect(wrapper.vm.tagApiService.filterIds).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.total).toBe(1);
    });

    it('should return sorted many to many assignment filter options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const options = wrapper.vm.assignmentFilterOptions;

        const expected = [
            'categories',
            'customers',
            'landingPages',
            'media',
            'newsletterRecipients',
            'orders',
            'products',
            'rules',
            'shippingMethods',
        ].map((value) => {
            return {
                value,
                label: `sw-settings-tag.list.assignments.filter.${value}`,
            };
        });

        expect(options).toEqual(expected);
    });

    it('should return count of filters', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(0).toEqual(wrapper.vm.filterCount);

        await wrapper.setData({
            emptyFilter: true,
        });

        expect(1).toEqual(wrapper.vm.filterCount);

        await wrapper.setData({
            duplicateFilter: true,
        });

        expect(2).toEqual(wrapper.vm.filterCount);
    });

    it('should open delete modal and request delete endpoint', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.showDeleteModal).toBeFalsy();

        wrapper.vm.onDelete('foo');

        expect(wrapper.vm.showDeleteModal).toBe('foo');

        wrapper.vm.onCloseDeleteModal();

        expect(wrapper.vm.showDeleteModal).toBeFalsy();

        wrapper.vm.onConfirmDelete('foo');

        expect(deleteEndpoint).toHaveBeenCalledTimes(1);
    });

    it('should open clone modal and request cl endpoint', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.showDuplicateModal).toBeFalsy();

        wrapper.vm.onDuplicate({ id: 'foo', name: 'bar' });

        expect(wrapper.vm.showDuplicateModal).toBe('foo');

        wrapper.vm.onCloseDuplicateModal();

        expect(wrapper.vm.showDuplicateModal).toBeFalsy();

        wrapper.vm.onConfirmDuplicate('foo');

        expect(cloneEndpoint).toHaveBeenCalledTimes(1);
    });

    it('should open detail modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onDetail('foo', 'bar', 'baz');
        await flushPromises();

        expect(wrapper.vm.showDetailModal).toBe('foo');
        expect(wrapper.vm.detailProperty).toBe('bar');
        expect(wrapper.vm.detailEntity).toBe('baz');
    });

    it('should offer the create action in the empty state when no tag exists', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
        ]);
        await flushPromises();
        await wrapper.setData({ isLoading: false, total: 0 });

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-settings-tag.list.titleEmptyStateList');

        const createButton = wrapper.find('.mt-empty-state__button .mt-button');

        expect(createButton.exists()).toBe(true);
        expect(createButton.attributes('disabled')).toBeUndefined();
    });

    it('should not offer the create action when a search has no hits', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
        ]);
        await flushPromises();
        await wrapper.setData({ isLoading: false, total: 0, term: 'zzzqqqnothing' });

        // a search without hits is not an empty tag list, so it offers no create action
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.mt-empty-state__button').exists()).toBe(false);
    });

    it.each([
        [
            'assignmentFilter',
            { assignmentFilter: ['products'] },
        ],
        [
            'emptyFilter',
            { emptyFilter: true },
        ],
        [
            'duplicateFilter',
            { duplicateFilter: true },
        ],
    ])('should not offer the create action when %s leaves no hits', async (_name, filter) => {
        const wrapper = await createWrapper([
            'tag.creator',
        ]);
        await flushPromises();
        await wrapper.setData({ isLoading: false, total: 0, ...filter });

        // the filter dropdown narrows an existing list, so this is a no-result state too
        expect(wrapper.vm.filterCount).toBeGreaterThan(0);
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.mt-empty-state__button').exists()).toBe(false);
    });

    it('should only count, sort and filter the areas the user can read', async () => {
        const wrapper = await createWrapper(
            [],
            [
                'product:read',
                'property_group_option:read',
                'property_group:read',
                'media:read',
            ],
        );
        await flushPromises();

        const aggregationNames = searchEndpoint.mock.calls[0][0].aggregations.map(({ name }) => name);
        expect(aggregationNames).toEqual(['products', 'media']);
        const columns = wrapper.findComponent('.sw-settings-tag-list__grid').props('columns');
        expect(columns.map(({ property }) => property)).toEqual(['name', 'products', 'media']);
        const filterOptions = wrapper.findComponent('.sw-settings-tag-list__filter-assignment-select').props('options');
        expect(filterOptions.map(({ value }) => value)).toEqual(['media', 'products']);

        const hint = wrapper.findComponent('.sw-settings-tag-list__unchecked-areas-hint');
        expect(hint.isVisible()).toBe(true);
        expect(hint.props('labels')).toEqual([
            'sw-settings-tag.detail.assignments.categories',
            'sw-settings-tag.detail.assignments.customers',
            'sw-settings-tag.detail.assignments.orders',
            'sw-settings-tag.detail.assignments.shippingMethods',
            'sw-settings-tag.detail.assignments.newsletterRecipients',
            'sw-settings-tag.detail.assignments.landingPages',
            'sw-settings-tag.detail.assignments.rules',
        ]);
    });

    it('should not show the unchecked areas hint when every area is readable', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-tag-list__unchecked-areas-hint').exists()).toBe(false);
    });

    it.each([
        ['every area is readable', allAreaReads, true],
        ['one area is not readable', allAreaReads.filter((read) => read !== 'rule:read'), false],
    ])('should offer merging tags only when %s', async (_name, areaReads, expected) => {
        // Merging deletes the merged tags, which would drop their assignments in unreadable areas
        const wrapper = await createWrapper(['tag.creator', 'tag.deleter'], areaReads);
        await flushPromises();

        expect(wrapper.find('.link[role="button"]').exists()).toBe(expected);
    });
});
