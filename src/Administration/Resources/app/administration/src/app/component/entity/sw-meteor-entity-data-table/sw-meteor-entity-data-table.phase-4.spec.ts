/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import type Repository from 'src/core/data/repository.data';
import SwMeteorEntityDataTable from './sw-meteor-entity-data-table.vue';
import type { SwMeteorEntityDataTableColumn } from './sw-meteor-entity-data-table.types';

const componentName = 'sw-meteor-entity-data-table';

type TestRepository = Repository<keyof EntitySchema.Entities>;

type TestRecord = {
    id: string;
    name: string;
};

type TestAdditionalContextButton = {
    key: string;
    label: string;
    type: 'default' | 'active' | 'critical';
};

type SwMeteorEntityDataTablePhase4TestProps = {
    repository: TestRepository;
    columns: SwMeteorEntityDataTableColumn[];
    records: TestRecord[];
    total: number;
    detailRoute?: string;
    allowEdit?: boolean;
    allowView?: boolean;
    allowDelete?: boolean;
    allowBulkDelete?: boolean;
    allowBulkEdit?: boolean;
    allowRowSelection?: boolean;
    showActions?: boolean;
    additionalContextButtons?: TestAdditionalContextButton[];
};

const columns: SwMeteorEntityDataTableColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
];

const records: TestRecord[] = [
    {
        id: 'record-1',
        name: 'First record',
    },
    {
        id: 'record-2',
        name: 'Second record',
    },
];

const additionalContextButtons: TestAdditionalContextButton[] = [
    {
        key: 'duplicate',
        label: 'Duplicate',
        type: 'default',
    },
];

const shopwareApplication = Shopware.Application as unknown as {
    view: {
        router?: {
            push: (to: unknown) => unknown;
        };
    };
};
const originalRouter = shopwareApplication.view.router;

const MtDataTableStub = {
    name: 'mt-data-table',
    emits: [
        'selection-change',
        'multiple-selection-change',
        'open-details',
        'context-select',
    ],
    props: [
        'dataSource',
        'columns',
        'currentPage',
        'paginationLimit',
        'paginationOptions',
        'paginationTotalItems',
        'sortBy',
        'sortDirection',
        'searchValue',
        'isLoading',
        'allowRowSelection',
        'allowBulkDelete',
        'allowBulkEdit',
        'selectedRows',
        'disableRowSelect',
        'disableSearch',
        'disableEdit',
        'disableDelete',
        'disableSettingsTable',
        'enableReload',
        'columnChanges',
        'additionalContextButtons',
    ],
    template: `
        <div class="mt-data-table-stub">
            <button class="mt-data-table-stub__select" type="button" @click="$emit('selection-change', { id: 'record-1', value: true })">Select</button>
            <button class="mt-data-table-stub__deselect" type="button" @click="$emit('selection-change', { id: 'record-1', value: false })">Deselect</button>
            <button class="mt-data-table-stub__bulk-select" type="button" @click="$emit('multiple-selection-change', { selections: ['record-1', 'record-2'], value: true })">Bulk select</button>
            <button class="mt-data-table-stub__bulk-deselect" type="button" @click="$emit('multiple-selection-change', { selections: ['record-1', 'record-2'], value: false })">Bulk deselect</button>
            <button class="mt-data-table-stub__open-details" type="button" @click="$emit('open-details', dataSource[0])">Open details</button>
            <button class="mt-data-table-stub__context-select" type="button" @click="$emit('context-select', { key: 'duplicate', data: dataSource[0] })">Context select</button>
        </div>
    `,
};

const SwBlockStub = {
    name: 'sw-block',
    props: [
        'name',
        'data',
    ],
    template: `
        <div>
            <slot />
        </div>
    `,
};

function createRepositoryMock(): TestRepository {
    return {
        search: jest.fn(),
    } as unknown as TestRepository;
}

function createWrapper(
    options: {
        props?: Partial<SwMeteorEntityDataTablePhase4TestProps>;
    } = {},
) {
    return mount(SwMeteorEntityDataTable, {
        props: {
            repository: createRepositoryMock(),
            columns,
            records,
            total: records.length,
            ...options.props,
        },
        global: {
            stubs: {
                'mt-data-table': MtDataTableStub,
                'sw-block': SwBlockStub,
            },
        },
    });
}

describe('src/app/component/entity/sw-meteor-entity-data-table Phase 4 behavior', () => {
    beforeEach(() => {
        delete _overridesMap[componentName];
        shopwareApplication.view.router = originalRouter;
    });

    afterEach(() => {
        delete _overridesMap[componentName];
        shopwareApplication.view.router = originalRouter;
    });

    it('maps ACL and action props to mt-data-table', async () => {
        const wrapper = createWrapper({
            props: {
                allowView: true,
                allowDelete: true,
                allowBulkDelete: true,
                allowBulkEdit: true,
                additionalContextButtons,
            },
        });

        const table = wrapper.findComponent(MtDataTableStub);

        expect(table.props()).toEqual(
            expect.objectContaining({
                allowBulkDelete: true,
                allowBulkEdit: true,
                disableEdit: false,
                disableDelete: false,
                additionalContextButtons,
            }),
        );

        await wrapper.setProps({
            allowDelete: false,
        });

        expect(table.props()).toEqual(
            expect.objectContaining({
                allowBulkDelete: false,
                disableDelete: true,
            }),
        );

        await wrapper.setProps({
            showActions: false,
            allowEdit: true,
            allowDelete: true,
        });

        expect(table.props()).toEqual(
            expect.objectContaining({
                disableEdit: true,
                disableDelete: true,
                additionalContextButtons: [],
            }),
        );
    });

    it('updates selected ids when a row is selected', async () => {
        const wrapper = createWrapper({
            props: {
                allowRowSelection: true,
            },
        });
        const table = wrapper.findComponent(MtDataTableStub);

        await wrapper.find('.mt-data-table-stub__select').trigger('click');

        expect(table.props('selectedRows')).toEqual(['record-1']);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                ['record-1'],
            ],
        ]);
    });

    it('removes a selected id when a row is deselected', async () => {
        const wrapper = createWrapper({
            props: {
                allowRowSelection: true,
            },
        });
        const table = wrapper.findComponent(MtDataTableStub);

        await wrapper.find('.mt-data-table-stub__select').trigger('click');
        await wrapper.find('.mt-data-table-stub__deselect').trigger('click');

        expect(table.props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                ['record-1'],
            ],
            [
                [],
            ],
        ]);
    });

    it('updates selected ids for bulk select and deselect', async () => {
        const wrapper = createWrapper({
            props: {
                allowRowSelection: true,
            },
        });
        const table = wrapper.findComponent(MtDataTableStub);

        await wrapper.find('.mt-data-table-stub__bulk-select').trigger('click');

        expect(table.props('selectedRows')).toEqual([
            'record-1',
            'record-2',
        ]);

        await wrapper.find('.mt-data-table-stub__bulk-deselect').trigger('click');

        expect(table.props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                [
                    'record-1',
                    'record-2',
                ],
            ],
            [
                [],
            ],
        ]);
    });

    it('emits open-details and routes when detailRoute is set', async () => {
        const routerPush = jest.fn();
        shopwareApplication.view.router = {
            push: routerPush,
        };
        const wrapper = createWrapper({
            props: {
                detailRoute: 'sw.test.detail',
            },
        });

        await wrapper.find('.mt-data-table-stub__open-details').trigger('click');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.test.detail',
            params: {
                id: 'record-1',
            },
        });
        expect(wrapper.emitted('open-details')).toEqual([
            [
                {
                    id: 'record-1',
                },
            ],
        ]);
    });

    it('emits open-details without routing when detailRoute is omitted', async () => {
        const routerPush = jest.fn();
        shopwareApplication.view.router = {
            push: routerPush,
        };
        const wrapper = createWrapper();

        await wrapper.find('.mt-data-table-stub__open-details').trigger('click');

        expect(routerPush).not.toHaveBeenCalled();
        expect(wrapper.emitted('open-details')).toEqual([
            [
                {
                    id: 'record-1',
                },
            ],
        ]);
    });

    it('forwards context-select payloads', async () => {
        const wrapper = createWrapper();
        const contextSelectPayload = {
            key: 'duplicate',
            data: records[0],
        };

        await wrapper.find('.mt-data-table-stub__context-select').trigger('click');

        expect(wrapper.emitted('context-select')).toEqual([
            [
                contextSelectPayload,
            ],
        ]);
    });
});
