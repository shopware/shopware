import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package services-settings
 */

const { Criteria } = Shopware.Data;
const { Context } = Shopware;

function createEntityCollectionMock(entityName, items = [], criteria = {}) {
    return new EntityCollection(
        '/route',
        entityName,
        {},
        criteria,
        items,
        items.length,
    );
}

const testAssociationName = 'testAssociation';

const ruleMock = {};

const categoryMock = {
    id: 'category1',
    parentId: null,
    name: 'Category 1',
    children: [],
};

const defaultProps = {
    rule: ruleMock,
    association: testAssociationName,
    categoriesCollection: createEntityCollectionMock('category', [categoryMock]),
    hideHeadline: true,
    hideSearch: true,
};

const categoryRepositoryMock = {
    search: jest.fn(async () => Promise.resolve(createEntityCollectionMock('category', [categoryMock]))),
};

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('sw-settings-rule-category-tree', { sync: true }),
        {
            props,
            global: {
                stubs: {
                    'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-settings-rule-tree': await wrapTestComponent('sw-settings-rule-tree'),
                    'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-card': await wrapTestComponent('sw-card'),
                },
                provide: {
                    repositoryFactory: {
                        create: () => categoryRepositoryMock,
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-rule/component/sw-settings-rule-category-tree', () => {
    afterEach(() => {
        jest.clearAllMocks();
        jest.clearAllTimers();
    });

    it('should not re-get tree items category entity is empty', async () => {
        await createWrapper({
            ...defaultProps,
            categoriesCollection: {
                ...createEntityCollectionMock('category', [categoryMock]),
                entity: null,
            },
        });
        await flushPromises();

        expect(categoryRepositoryMock.search).toHaveBeenCalledTimes(0);
    });

    it.each([{ expected: true }, { expected: false }])(
        'should hide headline: $expected',
        async ({ expected }) => {
            const wrapper = await createWrapper({
                ...defaultProps,
                hideHeadline: expected,
            });
            await flushPromises();

            expect(wrapper.find('.sw-tree-actions__headline').exists()).toBe(
                !expected,
            );
        },
    );

    it.each([{ expected: true }, { expected: false }])(
        'should hide search: $expected',
        async ({ expected }) => {
            const wrapper = await createWrapper({
                ...defaultProps,
                hideSearch: expected,
            });
            await flushPromises();

            expect(wrapper.find('.sw-tree__search').exists()).toBe(!expected);
        },
    );

    it('should load categories with association', async () => {
        await createWrapper();
        await flushPromises();

        const criteria = new Criteria(1, 500);
        criteria
            .getAssociation(testAssociationName)
            .addFilter(Criteria.equals('id', ruleMock.id));
        criteria.addFilter(Criteria.equals('parentId', null));

        expect(categoryRepositoryMock.search).toHaveBeenNthCalledWith(
            1,
            criteria,
            Context.api,
        );
    });

    it('should search tree items by card search field input', async () => {
        jest.useFakeTimers();
        const term = 'test';

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-simple-search-field input').setValue(term);
        await wrapper.find('.sw-simple-search-field input').trigger('update:value');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        const criteria = new Criteria(1, 500);
        criteria
            .getAssociation(testAssociationName)
            .addFilter(Criteria.equals('id', ruleMock.id));
        criteria.addFilter(Criteria.contains('name', term));

        expect(categoryRepositoryMock.search).toHaveBeenCalledTimes(2);
        expect(categoryRepositoryMock.search).toHaveBeenLastCalledWith(
            criteria,
            Context.api,
        );

        // trigger re-run to test filters filter
        await wrapper.find('.sw-simple-search-field input').setValue(term);
        await wrapper.find('.sw-simple-search-field input').trigger('update:value');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(categoryRepositoryMock.search).toHaveBeenCalledTimes(3);
        expect(categoryRepositoryMock.search.mock.calls[2][0].filters).toEqual([
            { type: 'contains', field: 'name', value: term },
        ]);
    });

    it('should check selected items', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('sw-settings-rule-tree-item').trigger('check-item', {});
        expect(wrapper.emitted()).toHaveProperty('on-selection');
    });

    it('should add new fetched categories', async () => {
        categoryRepositoryMock.search.mockResolvedValueOnce(
            createEntityCollectionMock('category', [
                {
                    ...categoryMock,
                    id: '1',
                    parentId: null,
                },
            ]),
        );
        categoryRepositoryMock.search.mockResolvedValueOnce(
            createEntityCollectionMock('category', [
                {
                    ...categoryMock,
                    id: '2',
                    parentId: '1',
                },
            ]),
        );

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.categories).toHaveLength(1);

        // needed to trigger function with parameter combination that only called by child component via event
        wrapper.vm.getTreeItems('1', null, true);
        await flushPromises();

        expect(wrapper.vm.categories).toHaveLength(2);
        expect(categoryRepositoryMock.search).toHaveBeenCalledTimes(2);
    });
});
