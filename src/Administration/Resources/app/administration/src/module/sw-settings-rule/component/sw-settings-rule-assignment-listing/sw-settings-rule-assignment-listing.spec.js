import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package services-settings
 */

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

const repositoryMock = {
    search: jest.fn(() => Promise.resolve([])),
};

const itemMock = {
    id: '1',
    name: 'Test',
    active: true,
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

const defaultProps = {
    items: createEntityCollectionMock('item', [itemMock]),
    repository: repositoryMock,
    columns: gridColumnsMock,
    criteriaLimit: 5,
    allowBulkEdit: true,
    allowDelete: true,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-settings-rule-assignment-listing', { sync: true }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-data-gird': await wrapTestComponent('sw-data-grid'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-button': await wrapTestComponent('sw-button'),
            },
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-assignment-listing', () => {
    it('should render column items', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const gridCells = wrapper.findAll('.sw-data-grid__cell--property');
        expect(gridCells).toHaveLength(gridColumnsMock.length);

        gridCells.forEach((cell, index) => {
            expect(cell.find('.sw-data-grid__cell-content').text().startsWith(gridColumnsMock[index].label)).toBe(true);
        });
    });

    it('should delete item per bulk delete modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-data-grid__select-all input').setChecked(true);
        await wrapper.find('.sw-data-grid__select-all input').trigger('change');

        expect(wrapper.find('.link-danger').exists()).toBe(true);
        await wrapper.find('.link-danger').trigger('click');

        expect(wrapper.find('.sw-entity-listing__confirm-bulk-delete-modal').exists()).toBe(true);
        await wrapper.find('.sw-button--danger').trigger('click');

        expect(wrapper.emitted()['delete-items']).toEqual([
            [
                {
                    1: itemMock,
                },
            ],
        ]);
    });
});
