/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

function createEntityCollectionMock(entityName, items = [], criteria = {}) {
    return new EntityCollection('/route', entityName, {}, criteria, items, items.length);
}

async function createWrapper(criteria = new Criteria(1, 25)) {
    return mount(await wrapTestComponent('sw-settings-rule-category-tree', { sync: true }), {
        props: {
            rule: {},
            association: 'categories',
            categoriesCollection: createEntityCollectionMock('category', [], criteria),
        },
        computed: {
            treeCriteria() {
                return criteria;
            },
            categoryRepository() {
                return {
                    search: jest.fn(() => Promise.resolve([])),
                };
            },
        },
        global: {
            stubs: {
                'sw-settings-rule-tree': await wrapTestComponent('sw-settings-rule-tree'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-field': await wrapTestComponent('sw-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-extension-component-section': true,
                'sw-ignore-class': true,
                'sw-icon': true,
                'sw-field-error': true,
            },
            provide: {
                validationService: {},
            },
        },
    });
}

describe('src/module/sw-settings-rule/component/sw-settings-rule-category-tree', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('should filter criteria correctly', async () => {
        const criteria = new Criteria(1, 25);
        criteria.addFilter(Criteria.contains('foo', 'baar'));

        const wrapper = await createWrapper(criteria);
        expect(wrapper.vm.categoryRepository.search.mock.lastCall[0].filters).toEqual([
            { field: 'foo', type: 'contains', value: 'baar' },
            { field: 'parentId', type: 'equals', value: null },
        ]);
    });

    it('should filter name and parentId criteria correctly', async () => {
        const criteria = new Criteria(1, 25);
        criteria.addFilter(Criteria.contains('name', 'testing'));
        criteria.addFilter(Criteria.equals('parentId', 'oldParentId'));

        const wrapper = await createWrapper(criteria);
        expect(wrapper.vm.categoryRepository.search.mock.lastCall[0].filters).toEqual([{ field: 'parentId', type: 'equals', value: null }]);
    });

    it('should filter criteria correctly and add term', async () => {
        const criteria = new Criteria(1, 25);
        criteria.addFilter(Criteria.contains('name', 'testing'));
        criteria.addFilter(Criteria.equals('parentId', 'oldParentId'));

        const wrapper = await createWrapper(criteria);
        await flushPromises();

        const search = wrapper.get('.sw-settings-rule-category-tree .sw-card__toolbar input');
        await search.setValue('anything');

        await flushPromises();
        jest.runAllTimers();

        expect(wrapper.vm.categoryRepository.search.mock.lastCall[0].filters).toEqual([{ field: 'name', type: 'contains', value: 'anything' }]);
    });
});
