import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Entity from 'src/core/data/entity.data';

/**
 * @package services-settings
 */

const { Criteria } = Shopware.Data;
const { Context } = Shopware;
const { cloneDeep } = Shopware.Utils.object;

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

const entityResultMock = [
    {
        id: 'test-id-1',
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
    search: jest.fn(() => Promise.resolve(createEntityCollectionMock('test_entity', entityResultMock))),
};

const shippingMethodRepositoryMock = {
    create: jest.fn(() => new Entity('shipping_method', 'test_id', {})),
    sync: jest.fn(() => Promise.resolve(createEntityCollectionMock('shipping_method', entityResultMock))),
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

const ruleMock = {
    id: 'ruleId',
};

const entityContextMock = {
    entityName: 'shipping_method',
    repository: entityRepositoryMock,
    addContext: {
        type: 'testType',
        entity: 'shipping_method',
        column: 'testColumn',
        gridColumns: gridColumnsMock,
    },
};

const defaultProps = {
    rule: ruleMock,
    entityContext: entityContextMock,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-settings-rule-add-assignment-modal', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-settings-rule-add-assignment-listing': await wrapTestComponent('sw-settings-rule-add-assignment-listing'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
            },
            provide: {
                repositoryFactory: {
                    create: () => shippingMethodRepositoryMock,
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-add-assignment-modal', () => {
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should close modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-modal__cancel-button').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-add-assignment-modal__cancel-button').trigger('click');

        expect(wrapper.emitted()).toHaveProperty('close-add-modal');
    });

    it.each([
        { name: 'category entity', entityName: 'category', expected: 'default' },
        { name: 'other entity', entityName: 'testEntity', expected: 'large' },
    ])('should define modal size by entity: $name', async ({ entityName, expected }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                entityName: entityName,
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-modal').attributes('variant')).toBe(expected);
    });

    it('should render category tree when entity is of category', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                entityName: 'category',
            },
        });
        await flushPromises();

        expect(wrapper.find('sw-settings-rule-category-tree').exists()).toBe(true);
    });

    it('should change selection', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-listing__card').exists()).toBe(true);

        const checkbox = wrapper.find('.sw-data-grid__row--0 .sw-field--checkbox input');

        expect(checkbox.element.checked).toBe(false);
        await checkbox.setChecked(true);
        await checkbox.trigger('change');
        expect(checkbox.element.checked).toBe(true);

        expect(wrapper.vm.selection).toEqual({
            [entityResultMock[0].id]: entityResultMock[0],
        });
    });

    it('should initialize component: category entity', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                entityName: 'category',
            },
        });
        await flushPromises();

        expect(wrapper.vm.entities.entity).toBe('category');
        expect(entityRepositoryMock.search).toHaveBeenCalledTimes(0);
    });

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom entity api', defaultApi: false },
    ])('should initialize component: other entity: $name', async ({ defaultApi }) => {
        const apiPath = '/custom-entity-api';

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                ...(!defaultApi && {
                    api: () => ({
                        ...Context.api,
                        apiPath,
                    }),
                }),
            },
        });
        await flushPromises();

        expect(wrapper.vm.repository).toEqual(entityRepositoryMock);

        expect(entityRepositoryMock.search).toHaveBeenCalledTimes(2);
        expect(entityRepositoryMock.search.mock.calls[0]).toEqual([
            new Criteria(1, 10),
            defaultApi ? Context.api : { ...Context.api, apiPath },
        ]);
    });

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom entity api', defaultApi: false },
    ])('should update entities on add: $name', async ({ defaultApi }) => {
        const apiPath = '/custom-entity-api';

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                addContext: {
                    ...defaultProps.entityContext.addContext,
                    type: 'one-to-many',
                },
                ...(!defaultApi && {
                    api: () => ({
                        ...Context.api,
                        apiPath,
                    }),
                }),
            },
        });
        await flushPromises();

        wrapper.vm.selection = cloneDeep({
            [entityResultMock[0].id]: entityResultMock[0],
        });

        await wrapper.find('.sw-settings-rule-add-assignment-modal__confirm-button').trigger('click');
        await flushPromises();

        expect(shippingMethodRepositoryMock.sync).toHaveBeenCalledTimes(1);
        expect(shippingMethodRepositoryMock.sync).toHaveBeenLastCalledWith(
            [
                {
                    ...entityResultMock[0],
                    testColumn: ruleMock.id,
                },
            ],
            defaultApi ? Context.api : { ...Context.api, apiPath },
        );
        expect(wrapper.emitted()).toHaveProperty('entities-saved');
    });

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom entity api', defaultApi: false },
    ])('should insert entities on add: $name', async ({ defaultApi }) => {
        const apiPath = '/custom-entity-api';

        const wrapper = await createWrapper({
            ...defaultProps,
            entityContext: {
                ...defaultProps.entityContext,
                addContext: {
                    ...defaultProps.entityContext.addContext,
                    type: 'not-one-to-many',
                },
                ...(!defaultApi && {
                    api: () => ({
                        ...Context.api,
                        apiPath,
                    }),
                }),
            },
        });
        await flushPromises();

        wrapper.vm.selection = cloneDeep({
            [entityResultMock[0].id]: entityResultMock[0],
        });

        await wrapper.find('.sw-settings-rule-add-assignment-modal__confirm-button').trigger('click');
        await flushPromises();

        expect(shippingMethodRepositoryMock.sync).toHaveBeenCalledTimes(1);
        expect(shippingMethodRepositoryMock.sync).toHaveBeenLastCalledWith(
            [new Entity('shipping_method', 'test_id', {
                ruleId: ruleMock.id,
                [entityContextMock.addContext.column]: entityResultMock[0].id,
            })],
            defaultApi ? Context.api : { ...Context.api, apiPath },
        );
        expect(wrapper.emitted()).toHaveProperty('entities-saved');
    });
});
