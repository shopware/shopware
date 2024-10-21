import { mount } from '@vue/test-utils';

/**
 * @package inventory
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
const deleteEndpoint = jest.fn(() => Promise.resolve());
const cloneEndpoint = jest.fn(() => Promise.resolve());

async function createWrapper(privileges = []) {
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
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(responseMock);
                            },

                            delete: deleteEndpoint,

                            clone: cloneEndpoint,
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
                    searchRankingService: {},
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
                    'sw-card': {
                        template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                    </div>
                `,
                    },
                    'sw-entity-listing': {
                        props: ['items'],
                        template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
                    },
                    'sw-context-menu-item': true,
                    'sw-search-bar': true,
                    'sw-icon': true,
                    'sw-loader': true,
                    'sw-button': true,
                    'sw-modal': true,
                    'sw-empty-state': true,
                    'sw-card-filter': true,
                    'sw-context-menu-divider': true,
                    'sw-switch-field': true,
                    'sw-multi-select': true,
                    'sw-context-button': true,
                    'sw-alert': true,
                    'sw-label': true,
                    'sw-text-field': true,
                    'sw-settings-tag-detail-modal': true,
                },
            },
        },
    );
}

describe('module/sw-settings-tag/page/sw-settings-tag-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new tag', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeTruthy();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a tag', async () => {
        const wrapper = await createWrapper([
            'tag.editor',
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a tag', async () => {
        const wrapper = await createWrapper([
            'tag.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should return summary of total connections', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const expected = {};
        Object.entries(connections).forEach(
            ([
                propertyName,
                count,
            ]) => {
                if (!count) {
                    return;
                }

                expected[propertyName] = count;
            },
        );
        const counts = wrapper.vm.getCounts('1');

        expect(counts).toEqual(expected);
    });

    it('should return total of single assignment', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getPropertyCounting('products', '1')).toBe(412);
        expect(wrapper.vm.getPropertyCounting('invalid', '1')).toBe(0);
        expect(wrapper.vm.getPropertyCounting('products', 'invalid')).toBe(0);
    });

    it('should use tag api service for duplicate filter', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            sortBy: 'products',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.total).toBe(2);

        await wrapper.setData({
            duplicateFilter: true,
        });

        wrapper.vm.onFilter();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tagApiService.filterIds).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.total).toBe(1);
    });

    it('should return sorted many to many assignment filter options', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

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
        await wrapper.vm.$nextTick();

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
        await wrapper.vm.$nextTick();

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
        await wrapper.vm.$nextTick();

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
        await wrapper.vm.$nextTick();

        wrapper.vm.onDetail('foo', 'bar', 'baz');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showDetailModal).toBe('foo');
        expect(wrapper.vm.detailProperty).toBe('bar');
        expect(wrapper.vm.detailEntity).toBe('baz');
    });
});
