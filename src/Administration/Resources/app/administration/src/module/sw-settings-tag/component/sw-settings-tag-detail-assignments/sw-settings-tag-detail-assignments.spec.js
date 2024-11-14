import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */

let parentComponent;
async function createWrapper() {
    const responseMockAll = [
        {
            id: '0',
            name: 'Parent 1 with tag',
        },
        {
            id: '1',
            parentId: '0',
            name: 'Variant 1 with inherited tag',
        },
        {
            id: '2',
            parentId: '0',
            name: 'Variant 2 with own tag',
        },
        {
            id: '3',
            parentId: '0',
            name: 'Parent 2 with different tag',
        },
        {
            id: '4',
            parentId: '3',
            name: 'Variant inheriting from Parent 2',
        },
    ];
    const aggregationsInherited = {
        tags: {
            buckets: [
                { key: '0', tags: { count: 1 } },
                { key: '1', tags: { count: 1 } },
                { key: '2', tags: { count: 1 } },
            ],
        },
        parentTags: {
            buckets: [
                { key: '0', parentTags: { count: 0 } },
                { key: '1', parentTags: { count: 1 } },
                { key: '2', parentTags: { count: 1 } },
                { key: '3', parentTags: { count: 0 } },
                { key: '4', parentTags: { count: 1 } },
            ],
        },
    };
    const responseMockSelected = [
        {
            id: '0',
            name: 'Parent with tag',
        },
        {
            id: '2',
            parentId: '0',
            name: 'Variant 2 with own tag',
        },
    ];
    const aggregations = {
        tags: {
            buckets: [
                { key: '0', tags: { count: 1 } },
                { key: '1', tags: { count: 0 } },
                { key: '2', tags: { count: 1 } },
                { key: '3', tags: { count: 1 } },
                { key: '4', tags: { count: 0 } },
            ],
        },
    };

    parentComponent = mount(
        await wrapTestComponent('sw-settings-tag-detail-modal', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    isNew: () => true,
                                };
                            },
                        }),
                    },
                    syncService: {},
                    acl: {
                        can: () => {
                            return true;
                        },
                    },
                },
                stubs: {
                    'sw-modal': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs', {
                        sync: true,
                    }),
                    'sw-tabs-item': true,
                    'sw-text-field': true,
                    'sw-settings-tag-detail-assignments': true,
                    'sw-button': true,
                    'sw-tabs-deprecated': true,
                    'sw-card-filter': true,
                },
            },
        },
    );

    const wrapper = mount(
        await wrapTestComponent('sw-settings-tag-detail-assignments', {
            sync: true,
        }),
        {
            props: {
                tag: {
                    id: '123',
                    isNew() {
                        return false;
                    },
                },
                toBeAdded: parentComponent.vm.assignmentsToBeAdded,
                toBeDeleted: parentComponent.vm.assignmentsToBeDeleted,
                initialCounts: {
                    products: 2,
                },
            },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: (criteria, context) => {
                                const response = context && context.inheritance ? responseMockAll : responseMockSelected;
                                response.aggregations =
                                    context && context.inheritance ? aggregationsInherited : aggregations;
                                response.total = response.length;

                                return Promise.resolve(response);
                            },
                            searchIds: jest.fn(() => Promise.resolve()),
                        }),
                    },
                    searchRankingService: {},
                },
                stubs: {
                    'sw-card': true,
                    'sw-card-section': true,
                    'sw-switch-field': true,
                    'sw-container': true,
                    'sw-text-field': true,
                    'sw-settings-tag-detail-assignments': true,
                    'sw-button': true,
                    'sw-tabs-deprecated': true,
                    'sw-card-filter': true,
                    'sw-icon': true,
                    'sw-data-grid': true,
                    'sw-checkbox-field': true,
                    'sw-inheritance-switch': true,
                    'sw-highlight-text': true,
                    'sw-product-variant-info': true,
                    'sw-media-preview-v2': true,
                    'sw-entity-listing': true,
                },
            },
        },
    );

    return wrapper;
}

describe('module/sw-settings-tag/component/sw-settings-tag-detail-assignments', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should fetch all and assigned entities', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.getList();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.entities).not.toBeNull();
        expect(Object.keys(wrapper.vm.preSelected)).toEqual([
            '0',
            '2',
        ]);

        await wrapper.setProps({
            toBeAdded: { orders: [] },
            toBeDeleted: { orders: [] },
        });
        await wrapper.vm.onAssignmentChange({
            entity: 'order',
            assignment: 'orders',
        });

        await wrapper.vm.onTermChange('');
        await wrapper.vm.onPageChange({
            page: 1,
            limit: 25,
        });

        expect(wrapper.vm.entities).not.toBeNull();
        expect(Object.keys(wrapper.vm.preSelected)).toEqual([
            '0',
            '2',
        ]);
    });

    it('should handle adding and removing of assignments including inheritance', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.getList();

        await wrapper.vm.$nextTick();

        [
            { id: '0', parentId: null, expected: false },
            { id: '1', parentId: '0', expected: true },
            { id: '2', parentId: '0', expected: true },
            { id: '3', parentId: null, expected: false },
            { id: '4', parentId: null, expected: true },
        ].forEach(({ id, parentId, expected }) => {
            expect(wrapper.vm.parentHasTags(id, parentId)).toEqual(expected);
        });

        [
            { id: '0', parentId: null, expected: false },
            { id: '1', parentId: '0', expected: true },
            { id: '2', parentId: '0', expected: false },
            { id: '3', parentId: null, expected: false },
            { id: '4', parentId: '3', expected: true },
        ].forEach(({ id, parentId, expected }) => {
            expect(wrapper.vm.isInherited(id, parentId)).toEqual(expected);
        });

        [
            { id: '0', parentId: null, expected: false },
            { id: '1', parentId: '0', expected: true },
            { id: '2', parentId: '0', expected: true },
            { id: '3', parentId: null, expected: false },
            { id: '4', parentId: '3', expected: false },
        ].forEach(({ id, parentId, expected }) => {
            expect(wrapper.vm.hasInheritedTag(id, parentId)).toEqual(expected);
        });

        expect(wrapper.vm.getCount('products')).toBe(2);

        // remove the assignment of the parent, parent shouldn't have tags anymore
        wrapper.vm.onSelectionChange([], { id: '0' }, false);
        expect(wrapper.vm.getCount('products')).toBe(1);

        expect(wrapper.emitted('remove-assignment')).toHaveLength(1);
        expect(wrapper.emitted('remove-assignment')[0]).toEqual([
            'products',
            '0',
            { id: '0' },
        ]);

        await parentComponent.vm.removeAssignment('products', '0', { id: '0' });
        await flushPromises();

        [
            { id: '1', parentId: '0', expected: false },
            { id: '2', parentId: '0', expected: false },
        ].forEach(({ id, parentId, expected }) => {
            expect(wrapper.vm.parentHasTags(id, parentId)).toEqual(expected);
        });

        // re-add the assignment of the parent
        wrapper.vm.onSelectionChange([], { id: '0' }, true);
        expect(wrapper.vm.getCount('products')).toBe(2);
        // remove direct assignment of variant 2, should become inherited
        wrapper.vm.onSelectionChange([], { id: '2' }, false);

        expect(wrapper.emitted('remove-assignment')).toHaveLength(2);
        expect(wrapper.emitted('remove-assignment')[1]).toEqual([
            'products',
            '2',
            { id: '2' },
        ]);

        await parentComponent.vm.removeAssignment('products', '2', { id: '2' });
        await flushPromises();

        expect(wrapper.emitted('add-assignment')).toHaveLength(1);
        expect(wrapper.emitted('add-assignment')[0]).toEqual([
            'products',
            '0',
            { id: '0' },
        ]);

        await parentComponent.vm.addAssignment('products', '0', { id: '0' });
        await flushPromises();

        [
            { id: '1', parentId: '0', expected: true },
            { id: '2', parentId: '0', expected: true },
        ].forEach(({ id, parentId, expected }) => {
            expect(wrapper.vm.isInherited(id, parentId)).toEqual(expected);
        });
    });

    it('should search for inheritance of newly added and removed entities if selected only are shown', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.getList();

        await wrapper.vm.$nextTick();

        wrapper.vm.onSelectionChange([], { id: '0' }, false);
        wrapper.vm.onSelectionChange([], { id: '3' }, true);

        await parentComponent.vm.removeAssignment('products', '0', { id: '0' });
        await parentComponent.vm.addAssignment('products', '3', { id: '3' });

        await wrapper.setData({
            showSelected: true,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.entityRepository.searchIds).toHaveBeenCalledTimes(2);
    });

    it('should return assignment associations', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const associations = wrapper.vm.assignmentAssociations;
        const properties = {
            products: 'product',
            media: 'media',
            categories: 'category',
            customers: 'customer',
            orders: 'order',
            shippingMethods: 'shipping_method',
            newsletterRecipients: 'newsletter_recipient',
            landingPages: 'landing_page',
            rules: 'rule',
        };
        const expected = Object.entries(properties).map(
            ([
                assignment,
                entity,
            ]) => {
                return {
                    name: `sw-settings-tag.detail.assignments.${assignment}`,
                    entity,
                    assignment,
                };
            },
        );

        expect(associations).toEqual(expected);
    });

    it('should return association columns', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const columns = wrapper.vm.assignmentAssociationsColumns;

        expect(columns[0].property).toBe('name');
    });

    it('should return entity columns', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const columns = wrapper.vm.entitiesColumns;

        expect(columns[0].property).toBe('name');
    });

    it('should return selected assignments', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        let selectedAssignments = wrapper.vm.selectedAssignments;

        expect(selectedAssignments['0'].id).toBe('0');
        expect(selectedAssignments['2'].id).toBe('2');

        await wrapper.setProps({
            toBeAdded: { products: [{ id: '3' }] },
            toBeDeleted: { products: [{ id: '2' }] },
        });

        selectedAssignments = wrapper.vm.selectedAssignments;

        expect(selectedAssignments['0'].id).toBe('0');
        expect(selectedAssignments['3'].id).toBe('3');
        expect(selectedAssignments.hasOwnProperty('2')).toBeFalsy();
    });

    it('should increase and decrease counts on non existent properties', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.countIncrease('foo');

        expect(wrapper.vm.counts.foo).toBe(1);

        wrapper.vm.countDecrease('bar');

        expect(wrapper.vm.counts.bar).toBe(0);
    });
});
