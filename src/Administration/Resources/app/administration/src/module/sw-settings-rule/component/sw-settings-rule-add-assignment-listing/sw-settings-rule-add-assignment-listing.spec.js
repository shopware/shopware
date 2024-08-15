import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package services-settings
 * @group disabledCompat
 */

const { Criteria } = Shopware.Data;
const { Context } = Shopware;

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

const entityResultMock = [
    {
        name: 'test-name',
        active: true,
        taxType: 'auto',
        translated: {
            name: 'test-name',
            type: 'test-type',
        },
    },
];

const entityRepositoryMock = {
    search: jest.fn(() => Promise.resolve(createEntityCollectionMock('shipping_method', entityResultMock))),
};

const gridColumnsMock = [
    {
        property: 'name',
        label: 'Name',
        rawData: true,
        sortable: true,
        allowEdit: false,
        dataIndex: 'name',
    },
    {
        property: 'active',
        label: 'Active',
        rawData: true,
        sortable: true,
        allowEdit: false,
        dataIndex: 'active',
    },
];

const entityContextMock = {
    id: 'uuid1',
    addContext: {
        gridColumns: gridColumnsMock,
        column: 'uuid1',
    },
    associationName: 'shippingMethod',
    criteria: jest.fn(),
    detailRoute: 'sw.settings.shipping.detail',
    entityName: 'shipping_method',
    gridColumns: null,
    repository: entityRepositoryMock,
};

const defaultProps = {
    ruleId: 'uuid1',
    entityContext: entityContextMock,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-settings-rule-add-assignment-listing', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-pagination': await wrapTestComponent('sw-pagination'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-product-variant-info': true,
                'sw-icon': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'sw-context-menu-item': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-data-grid-skeleton': true,
                'sw-field-error': true,
                'sw-select-field': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-help-text': true,
            },
        },
    });
}

describe('src/module/sw-settings-rule/component/sw-settings-rule-add-assignment-listing', () => {
    afterEach(() => {
        jest.clearAllMocks();
        jest.clearAllTimers();
    });

    it('should add entity context associations', async () => {
        const testAssociation = 'testAssociation';

        entityRepositoryMock.search.mockResolvedValueOnce(createEntityCollectionMock('testEntity', [
            {
                ...entityResultMock[0],
                testAssociation: [
                    {
                        id: defaultProps.ruleId,
                    },
                ],
            },
        ]));

        await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                addContext: {
                    ...entityContextMock.addContext,
                    association: testAssociation,
                },
            },
        });
        await flushPromises();

        const criteria = new Criteria(1, 10);
        criteria.addAssociation(testAssociation);
        criteria.getAssociation(testAssociation)
            .addFilter(Criteria.equals('id', defaultProps.ruleId));

        expect(entityRepositoryMock.search).toHaveBeenNthCalledWith(1, criteria, expect.any(Object));
    });

    it('should add product options group association', async () => {
        const productEntity = 'product';

        await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                entityName: productEntity,
            },
        });
        await flushPromises();

        const criteria = new Criteria(1, 10);
        criteria.addAssociation('options.group');

        expect(entityRepositoryMock.search).toHaveBeenNthCalledWith(1, criteria, expect.any(Object));
    });

    it('should search for assigment items', async () => {
        const wrapper = await createWrapper();

        // check for loading state
        expect(wrapper.find('.sw-settings-rule-add-assignment-listing__grid').attributes('is-loading')).toBe('true');
        await flushPromises();
        expect(wrapper.find('.sw-settings-rule-add-assignment-listing__grid').attributes('is-loading')).toBeUndefined();

        expect(entityRepositoryMock.search).toHaveBeenNthCalledWith(1, expect.any(Criteria), Context.api);
        expect(wrapper.vm.total).toBe(entityResultMock.length);
    });

    it('should search for assigment items with entity api', async () => {
        const context = {
            ...Context.api,
            apiPath: '/test',
        };

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                api: () => context,
            },
        });
        await flushPromises();

        expect(entityRepositoryMock.search).toHaveBeenNthCalledWith(1, expect.any(Criteria), context);
        expect(wrapper.vm.total).toBe(entityResultMock.length);
    });

    it.each([
        { name: 'defaultField', defaultField: true },
        { name: 'context search column', defaultField: false },
    ])('should search for assigment items with search term with $name', async ({ defaultField }) => {
        jest.useFakeTimers();

        const testSearchColumn = 'testColumn';
        const testSearchTerm = 'test';

        const entityContext = defaultField ? entityContextMock : {
            ...entityContextMock,
            addContext: {
                ...entityContextMock.addContext,
                searchColumn: testSearchColumn,
            },
        };

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext,
        });
        await flushPromises();

        await wrapper.find('.sw-simple-search-field input').setValue(testSearchTerm);
        await wrapper.find('.sw-simple-search-field input').trigger('input');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        const criteria = new Criteria(1, 10);
        criteria.addFilter(Criteria.contains(defaultField ? 'name' : testSearchColumn, testSearchTerm));

        expect(entityRepositoryMock.search).toHaveBeenCalledTimes(2);
        expect(entityRepositoryMock.search).toHaveBeenLastCalledWith(criteria, expect.any(Object));
    });

    it('should change selected items', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 input').setChecked(true);
        await wrapper.find('.sw-data-grid__row--0 input').trigger('change');

        expect(wrapper.emitted()).toHaveProperty('select-item');
    });

    it('should make assigned item unselectable', async () => {
        entityRepositoryMock.search.mockResolvedValueOnce(createEntityCollectionMock('testEntity', [
            {
                ...entityResultMock[0],
                test: defaultProps.ruleId,
            },
        ]));

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                addContext: {
                    ...entityContextMock.addContext,
                    column: 'test',
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 input').attributes()).toHaveProperty('disabled');
    });

    it('should make assigned item unselectable by association', async () => {
        entityRepositoryMock.search.mockResolvedValueOnce(createEntityCollectionMock('testEntity', [
            {
                ...entityResultMock[0],
                test: ['a'],
            },
        ]));

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                addContext: {
                    ...entityContextMock.addContext,
                    association: 'test',
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 input').attributes()).toHaveProperty('disabled');
    });

    it.each([
        { name: 'auto', taxType: 'auto', expected: 'sw-settings-shipping.shippingCostOptions.auto' },
        { name: 'empty', taxType: '', expected: '' },
        { name: 'no option', taxType: 'test', expected: '' },
    ])('should add shipping tax type label: $name', async ({ taxType, expected }) => {
        entityRepositoryMock.search.mockResolvedValueOnce(createEntityCollectionMock(
            'testEntity',
            [
                {
                    ...entityResultMock[0],
                    taxType,
                },
            ],
        ));

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...entityContextMock,
                addContext: {
                    ...entityContextMock.addContext,
                    gridColumns: [
                        {
                            property: 'taxType',
                        },
                    ],
                },
            },
        });
        await flushPromises();

        const gridContent = wrapper.find('.sw-data-grid__cell--taxType .sw-data-grid__cell-content');
        expect(gridContent.text()).toBe(expected);
    });

    it('should paginate items', async () => {
        entityRepositoryMock.search.mockResolvedValue(createEntityCollectionMock(
            'testEntity',
            Array(30).fill(entityResultMock[0]).map((item, index) => {
                return {
                    ...item,
                    name: `test-name-${index}`,
                };
            }),
        ));

        const wrapper = await createWrapper();
        await flushPromises();

        expect(entityRepositoryMock.search).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('.sw-pagination__list-item')).toHaveLength(3);
        expect(wrapper.find('.sw-pagination .is-active').text()).toBe('1');

        await wrapper.find('.sw-pagination__page-button-next').trigger('click');
        await flushPromises();

        expect(entityRepositoryMock.search).not.toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-pagination .is-active').text()).toBe('2');

        await wrapper.find('.sw-pagination__page-button-prev').trigger('click');
        await flushPromises();

        expect(entityRepositoryMock.search).not.toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-pagination .is-active').text()).toBe('1');

        entityRepositoryMock.search.mockClear();
    });
});
