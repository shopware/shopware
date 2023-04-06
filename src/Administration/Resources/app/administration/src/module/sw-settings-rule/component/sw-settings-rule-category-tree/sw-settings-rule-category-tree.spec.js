/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import swRuleTree from 'src/module/sw-settings-rule/component/sw-settings-rule-tree';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-card-filter';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import swSettingsRuleCategoryTree from 'src/module/sw-settings-rule/component/sw-settings-rule-category-tree';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

Shopware.Component.register('sw-settings-rule-category-tree', swSettingsRuleCategoryTree);
Shopware.Component.extend('sw-settings-rule-tree', 'sw-tree', swRuleTree);

function createEntityCollectionMock(entityName, items = [], criteria = {}) {
    return new EntityCollection('/route', entityName, {}, criteria, items, items.length);
}

async function createWrapper(criteria = new Criteria(1, 25)) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-rule-category-tree'), {
        localVue,
        stubs: {
            'sw-settings-rule-tree': await Shopware.Component.build('sw-settings-rule-tree'),
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-card-filter': await Shopware.Component.build('sw-card-filter'),
            'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-extension-component-section': true,
            'sw-ignore-class': true,
            'sw-icon': true,
            'sw-field-error': true,
        },
        propsData: {
            rule: {},
            association: 'categories',
            categoriesCollection: createEntityCollectionMock('category', [], criteria),
        },
        provide: {
            validationService: {},
        },
        computed: {
            treeCriteria() {
                return criteria;
            },
            categoryRepository() {
                return {
                    search: jest.fn(() => Promise.resolve([]))
                };
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
        const search = wrapper.find('.sw-settings-rule-category-tree .sw-card__toolbar input');
        await search.setValue('anything');

        await flushPromises();
        jest.runAllTimers();

        expect(wrapper.vm.categoryRepository.search.mock.lastCall[0].filters).toEqual([{ field: 'name', type: 'contains', value: 'anything' }]);
    });
});
