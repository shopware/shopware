/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { h, nextTick, ref } from 'vue';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import type Repository from 'src/core/data/repository.data';
import SwMeteorEntityDataTable from './sw-meteor-entity-data-table.vue';

const componentName = 'sw-meteor-entity-data-table';

type SlotRenderers = Record<string, string | ((slotProps: { source: string }) => ReturnType<typeof h>)>;

type TestRepository = Repository<keyof EntitySchema.Entities>;

type TestColumn = {
    label: string;
    property: string;
    renderer: 'text' | 'number' | 'price' | 'badge';
    position: number;
};

type TestRecord = {
    id: string;
    name: string;
};

type TestAdditionalContextButton = {
    key: string;
    label: string;
    type: 'default' | 'active' | 'critical';
};

type SwMeteorEntityDataTableTestProps = {
    repository: TestRepository;
    columns: TestColumn[];
    records?: TestRecord[] | null;
    total?: number | null;
    initialPage?: number;
    initialLimit?: number;
    isLoading?: boolean;
    allowRowSelection?: boolean;
    disableSearch?: boolean;
    showSettings?: boolean;
    enableReload?: boolean;
    allowEdit?: boolean;
    allowDelete?: boolean;
    allowBulkDelete?: boolean;
    allowBulkEdit?: boolean;
    additionalContextButtons?: TestAdditionalContextButton[];
};

const columns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
        renderer: 'text',
        position: 0,
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

function createRepositoryMock(): TestRepository {
    return {
        search: jest.fn(),
    } as unknown as TestRepository;
}

const MtDataTableStub = {
    name: 'mt-data-table',
    props: [
        'dataSource',
        'columns',
        'currentPage',
        'paginationLimit',
        'paginationTotalItems',
        'sortBy',
        'sortDirection',
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
            <div class="mt-data-table-stub__toolbar">
                <slot name="toolbar" source="toolbar" />
            </div>
            <div class="mt-data-table-stub__empty-state">
                <slot name="empty-state" source="empty-state" />
            </div>
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
        <div
            class="sw-block-stub"
            :data-block-name="name"
        >
            <slot />
        </div>
    `,
};

function defaultRequiredProps(): Pick<SwMeteorEntityDataTableTestProps, 'repository' | 'columns'> {
    return {
        repository: createRepositoryMock(),
        columns,
    };
}

function defaultProps(): Pick<SwMeteorEntityDataTableTestProps, 'repository' | 'columns' | 'records' | 'total'> {
    return {
        ...defaultRequiredProps(),
        records,
        total: 42,
    };
}

function createWrapper(
    options: {
        props?: Partial<SwMeteorEntityDataTableTestProps>;
        slots?: SlotRenderers;
    } = {},
) {
    return mount(SwMeteorEntityDataTable, {
        props: {
            ...defaultProps(),
            ...options.props,
        },
        slots: options.slots,
        global: {
            stubs: {
                'mt-data-table': MtDataTableStub,
                'sw-block': SwBlockStub,
            },
        },
    });
}

function createWrapperWithoutControlledData(
    options: {
        props?: Partial<SwMeteorEntityDataTableTestProps>;
        slots?: SlotRenderers;
    } = {},
) {
    return mount(SwMeteorEntityDataTable, {
        props: {
            ...defaultRequiredProps(),
            ...options.props,
        },
        slots: options.slots,
        global: {
            stubs: {
                'mt-data-table': MtDataTableStub,
                'sw-block': SwBlockStub,
            },
        },
    });
}

function findBlock(wrapper: VueWrapper, name: string) {
    const block = wrapper.findAllComponents(SwBlockStub).find((blockWrapper) => {
        return blockWrapper.props('name') === name;
    });

    expect(block).toBeDefined();

    return block!;
}

describe('src/app/component/entity/sw-meteor-entity-data-table', () => {
    beforeEach(() => {
        delete _overridesMap[componentName];
    });

    afterEach(() => {
        delete _overridesMap[componentName];
    });

    it('renders mt-data-table', () => {
        const wrapper = createWrapper();

        expect(wrapper.findComponent(MtDataTableStub).exists()).toBe(true);
    });

    it('maps the phase 1 props to mt-data-table', () => {
        const wrapper = createWrapper({
            props: {
                initialPage: 3,
                initialLimit: 15,
                isLoading: true,
                allowRowSelection: true,
                disableSearch: true,
                showSettings: false,
                enableReload: true,
                additionalContextButtons,
            },
        });

        const table = wrapper.findComponent(MtDataTableStub);

        expect(table.props()).toEqual(
            expect.objectContaining({
                dataSource: records,
                columns,
                currentPage: 3,
                paginationLimit: 15,
                paginationTotalItems: 42,
                sortBy: '',
                sortDirection: 'ASC',
                isLoading: true,
                allowRowSelection: true,
                allowBulkDelete: false,
                allowBulkEdit: false,
                selectedRows: [],
                disableRowSelect: [],
                disableSearch: true,
                disableEdit: true,
                disableDelete: true,
                disableSettingsTable: true,
                enableReload: true,
                columnChanges: {},
                additionalContextButtons,
            }),
        );
    });

    it('enables edit, delete, and bulk actions on mt-data-table when ACL props allow them', () => {
        const wrapper = createWrapper({
            props: {
                allowEdit: true,
                allowDelete: true,
                allowBulkDelete: true,
                allowBulkEdit: true,
            },
        });

        const table = wrapper.findComponent(MtDataTableStub);

        expect(table.props()).toEqual(
            expect.objectContaining({
                allowBulkDelete: true,
                allowBulkEdit: true,
                disableEdit: false,
                disableDelete: false,
            }),
        );
    });

    it('passes empty table data when records and total are omitted', () => {
        const wrapper = createWrapperWithoutControlledData();

        const table = wrapper.findComponent(MtDataTableStub);
        const setupStateKeys = Object.keys(
            (wrapper.vm.$ as unknown as { setupState: Record<string, unknown> }).setupState,
        );

        expect(table.props('dataSource')).toEqual([]);
        expect(table.props('paginationTotalItems')).toBe(0);
        expect(setupStateKeys).toContain('dataSource');
        expect(setupStateKeys).toContain('totalItems');
        expect(setupStateKeys).not.toContain('records');
        expect(setupStateKeys).not.toContain('total');
    });

    it('reacts to controlled records and total prop updates', async () => {
        const wrapper = createWrapper({
            props: {
                total: null,
            },
        });
        const updatedRecords = [
            {
                id: 'record-3',
                name: 'Third record',
            },
        ];

        await wrapper.setProps({
            records: updatedRecords,
            total: 12,
        });

        const table = wrapper.findComponent(MtDataTableStub);

        expect(table.props('dataSource')).toEqual(updatedRecords);
        expect(table.props('paginationTotalItems')).toBe(12);

        await wrapper.setProps({
            total: null,
        });

        expect(table.props('paginationTotalItems')).toBe(updatedRecords.length);
    });

    it('forwards toolbar and empty-state slots', () => {
        const wrapper = createWrapper({
            slots: {
                toolbar: ({ source }: { source: string }) => h('span', { class: 'toolbar-slot' }, source),
                'empty-state': ({ source }: { source: string }) => h('span', { class: 'empty-state-slot' }, source),
            },
        });

        expect(wrapper.find('.toolbar-slot').text()).toBe('toolbar');
        expect(wrapper.find('.empty-state-slot').text()).toBe('empty-state');
    });

    it('renders the initial sw-block extension points with data scopes', () => {
        const wrapper = createWrapper({
            props: {
                initialPage: 2,
                initialLimit: 10,
            },
        });

        [
            'sw_meteor_entity_data_table',
            'sw_meteor_entity_data_table_before_table',
            'sw_meteor_entity_data_table_table',
            'sw_meteor_entity_data_table_toolbar',
            'sw_meteor_entity_data_table_empty_state',
            'sw_meteor_entity_data_table_after_table',
        ].forEach((blockName) => {
            expect(findBlock(wrapper, blockName).exists()).toBe(true);
        });

        const rootBlockData = findBlock(wrapper, 'sw_meteor_entity_data_table').props('data') as Record<string, unknown>;

        expect(rootBlockData).toEqual(
            expect.objectContaining({
                dataSource: records,
                totalItems: 42,
                page: 2,
                limit: 10,
                selectedIds: [],
                normalizedColumns: columns,
            }),
        );
        expect(typeof rootBlockData.loadData).toBe('function');
    });

    it('allows overrideComponentSetup to override public setup state', async () => {
        const overrideRecords = [
            {
                id: 'override-record',
                name: 'Override record',
            },
        ];

        overrideComponentSetup<typeof SwMeteorEntityDataTable>()(componentName, () => ({
            dataSource: ref(overrideRecords),
            totalItems: ref(1),
            page: ref(7),
            limit: ref(100),
        }));

        const wrapper = createWrapper();

        await nextTick();

        const table = wrapper.findComponent(MtDataTableStub);

        expect(table.props('dataSource')).toEqual(overrideRecords);
        expect(table.props('paginationTotalItems')).toBe(1);
        expect(table.props('currentPage')).toBe(7);
        expect(table.props('paginationLimit')).toBe(100);
    });
});
