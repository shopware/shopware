/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { h, nextTick, ref } from 'vue';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import { normalizeSwMeteorEntityDataTableColumns } from './sw-meteor-entity-data-table-column-normalizer';
import SwMeteorEntityDataTable from './sw-meteor-entity-data-table.vue';
import type { SwMeteorEntityDataTableColumn } from './sw-meteor-entity-data-table.types';

const componentName = 'sw-meteor-entity-data-table';

type SlotRenderers = Record<string, string | ((slotProps: { source: string }) => ReturnType<typeof h>)>;

type TestRepository = Repository<keyof EntitySchema.Entities>;

type TestColumn = SwMeteorEntityDataTableColumn;

type TestRecord = {
    id: string;
    name: string;
};

type TestAdditionalContextButton = {
    key: string;
    label: string;
    type: 'default' | 'active' | 'critical';
};

type TestSearchMock = jest.Mock<
    Promise<EntityCollection<keyof EntitySchema.Entities>>,
    [CriteriaType, typeof Shopware.Context.api]
>;

type SwMeteorEntityDataTableTestProps = {
    repository: TestRepository;
    columns: TestColumn[];
    records?: TestRecord[] | null;
    total?: number | null;
    criteria?: CriteriaType | null;
    context?: ApiContext | null;
    initialPage?: number;
    initialLimit?: number;
    isLoading?: boolean;
    disableDataFetching?: boolean;
    detailRoute?: string;
    allowRowSelection?: boolean;
    disableSearch?: boolean;
    searchValue?: string;
    showSettings?: boolean;
    showActions?: boolean;
    enableReload?: boolean;
    allowEdit?: boolean;
    allowView?: boolean;
    allowDelete?: boolean;
    allowBulkDelete?: boolean;
    allowBulkEdit?: boolean;
    additionalContextButtons?: TestAdditionalContextButton[];
};

const columns: TestColumn[] = [{ label: 'Name', property: 'name', renderer: 'text', position: 0 }];

const records: TestRecord[] = [
    { id: 'record-1', name: 'First record' },
    { id: 'record-2', name: 'Second record' },
];

const additionalContextButtons: TestAdditionalContextButton[] = [{ key: 'duplicate', label: 'Duplicate', type: 'default' }];

const shopwareApplication = Shopware.Application as unknown as {
    view: {
        i18n: {
            global: {
                t: (key: string) => string;
            };
        };
    };
};
const shopwareI18n = shopwareApplication.view.i18n.global;
const originalTranslate = shopwareI18n.t;
const { Criteria } = Shopware.Data;

function createSearchResult(
    resultRecords: TestRecord[] = records,
    total = resultRecords.length,
): EntityCollection<keyof EntitySchema.Entities> {
    return Object.assign([...resultRecords], { total }) as unknown as EntityCollection<keyof EntitySchema.Entities>;
}

function createRepositoryMock(searchResult = createSearchResult()): TestRepository {
    const search: TestSearchMock = jest.fn<ReturnType<TestSearchMock>, Parameters<TestSearchMock>>();
    search.mockResolvedValue(searchResult);

    return {
        search,
    } as unknown as TestRepository;
}

function getSearchMock(repository: TestRepository): TestSearchMock {
    return (repository as unknown as { search: TestSearchMock }).search;
}

const MtDataTableStub = {
    name: 'mt-data-table',
    emits: [
        'sort-change',
        'pagination-current-page-change',
        'pagination-limit-change',
        'search-value-change',
        'reload',
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
            <div class="mt-data-table-stub__toolbar">
                <slot name="toolbar" source="toolbar" />
            </div>
            <div class="mt-data-table-stub__empty-state">
                <slot name="empty-state" source="empty-state" />
            </div>
            <button class="mt-data-table-stub__sort" type="button" @click="$emit('sort-change', columns[0]?.property, 'DESC')">Sort</button>
            <button class="mt-data-table-stub__page" type="button" @click="$emit('pagination-current-page-change', 3)">Page</button>
            <button class="mt-data-table-stub__limit" type="button" @click="$emit('pagination-limit-change', 50)">Limit</button>
            <button class="mt-data-table-stub__search" type="button" @click="$emit('search-value-change', 'needle')">Search</button>
            <button class="mt-data-table-stub__reload" type="button" @click="$emit('reload')">Reload</button>
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

function getTableColumns(wrapper: VueWrapper): TestColumn[] {
    return wrapper.findComponent(MtDataTableStub).props('columns') as TestColumn[];
}

function getLastSearchCriteria(repository: TestRepository): CriteriaType {
    const searchMock = getSearchMock(repository);
    const lastCall = searchMock.mock.calls[searchMock.mock.calls.length - 1];

    expect(lastCall).toBeDefined();

    return lastCall[0];
}

describe('src/app/component/entity/sw-meteor-entity-data-table', () => {
    beforeEach(() => {
        delete _overridesMap[componentName];
        shopwareI18n.t = originalTranslate;
    });

    afterEach(() => {
        delete _overridesMap[componentName];
        shopwareI18n.t = originalTranslate;
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

    it('fails when a column property is missing', () => {
        expect(() => {
            normalizeSwMeteorEntityDataTableColumns([
                {
                    label: 'Name',
                },
            ]);
        }).toThrow('Please specify a "property" to render a column');
    });

    it('translates column labels with the Administration fallback', () => {
        shopwareI18n.t = (key: string) => {
            if (key === 'sw.test.translated-column') {
                return 'Translated column';
            }

            return '';
        };

        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'sw.test.translated-column',
                        property: 'translated',
                    },
                    {
                        label: 'sw.test.missing-column',
                        property: 'missing',
                    },
                ],
            },
        });

        expect(getTableColumns(wrapper).map((column) => column.label)).toEqual([
            'Translated column',
            'sw.test.missing-column',
        ]);
    });

    it('assigns stable default positions and text renderers', () => {
        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'Name',
                        property: 'name',
                    },
                    {
                        label: 'Company',
                        property: 'company',
                    },
                    {
                        label: 'Created at',
                        property: 'createdAt',
                        position: 450,
                    },
                ],
            },
        });

        expect(getTableColumns(wrapper)).toEqual([
            {
                label: 'Name',
                property: 'name',
                renderer: 'text',
                position: 0,
            },
            {
                label: 'Company',
                property: 'company',
                renderer: 'text',
                position: 100,
            },
            {
                label: 'Created at',
                property: 'createdAt',
                renderer: 'text',
                position: 450,
            },
        ]);
    });

    it('normalizes numeric, pixel, and auto column widths', () => {
        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'Numeric width',
                        property: 'numericWidth',
                        width: 100,
                    },
                    {
                        label: 'Numeric string width',
                        property: 'numericStringWidth',
                        width: '100',
                    },
                    {
                        label: 'Pixel width',
                        property: 'pixelWidth',
                        width: '100px',
                    },
                    {
                        label: 'Auto width',
                        property: 'autoWidth',
                        width: 'auto',
                    },
                ],
            },
        });
        const normalizedColumns = getTableColumns(wrapper);

        expect(normalizedColumns.map((column) => column.width)).toEqual([
            100,
            100,
            100,
            undefined,
        ]);
        expect(normalizedColumns[3]).not.toHaveProperty('width');
    });

    it('keeps dataIndex sort mapping metadata while passing Meteor columns', async () => {
        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'Translated name',
                        property: 'translated.name',
                        dataIndex: 'name',
                        naturalSorting: true,
                        useCustomSort: true,
                    },
                ],
            },
        });
        expect(getTableColumns(wrapper)[0]).toEqual({
            label: 'Translated name',
            property: 'translated.name',
            renderer: 'text',
            position: 0,
        });

        await wrapper.find('.mt-data-table-stub__sort').trigger('click');

        expect(wrapper.emitted('sort-change')).toEqual([
            [
                {
                    property: 'translated.name',
                    dataIndex: 'name',
                    direction: 'DESC',
                    naturalSorting: true,
                },
            ],
        ]);
    });

    it('fails loudly for unsupported legacy-only column fields', () => {
        expect(() => {
            normalizeSwMeteorEntityDataTableColumns([
                {
                    label: 'Name',
                    property: 'name',
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                },
            ]);
        }).toThrow(
            'unsupported field(s): routerLink, inlineEdit. These legacy sw-data-grid fields require upstream mt-data-table support',
        );
    });

    it('passes empty table data when records and total are omitted', () => {
        const wrapper = createWrapperWithoutControlledData();

        const table = wrapper.findComponent(MtDataTableStub);
        const setupStateKeys = Object.keys((wrapper.vm.$ as unknown as { setupState: Record<string, unknown> }).setupState);

        expect(table.props('dataSource')).toEqual([]);
        expect(table.props('paginationTotalItems')).toBe(0);
        expect(setupStateKeys).toContain('dataSource');
        expect(setupStateKeys).toContain('totalItems');
        expect(setupStateKeys).not.toContain('records');
        expect(setupStateKeys).not.toContain('total');
    });

    it('loads records in self-fetching mode with a cloned criteria', async () => {
        const searchResult = createSearchResult(
            [
                {
                    id: 'record-3',
                    name: 'Third record',
                },
            ],
            37,
        );
        const repository = createRepositoryMock(searchResult);
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        } as ApiContext;
        const criteria = new Criteria(9, 99);
        criteria.addFilter(Criteria.equals('active', true));
        criteria.addPostFilter(Criteria.equals('visible', true));
        criteria.addAssociation('manufacturer');
        criteria.getAssociation('manufacturer').addFilter(Criteria.equals('name', 'ACME'));
        criteria.addAggregation(Criteria.count('count-id', 'id'));
        criteria.addIncludes({
            product: [
                'id',
                'name',
            ],
        });
        criteria.addFields('id', 'name');
        criteria.addGrouping('manufacturerId');
        criteria.addGroupField('manufacturerId');
        criteria.setTotalCountMode(2);
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        const originalCriteriaPayload = criteria.parse();
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
                criteria,
                context,
                initialPage: 2,
                initialLimit: 10,
                searchValue: 'shirt',
            },
        });

        await flushPromises();

        const searchMock = getSearchMock(repository);
        const usedCriteria = getLastSearchCriteria(repository);
        const usedCriteriaPayload = usedCriteria.parse();

        expect(searchMock).toHaveBeenCalledTimes(1);
        expect(usedCriteria).not.toBe(criteria);
        expect(criteria.parse()).toEqual(originalCriteriaPayload);
        expect(usedCriteriaPayload).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
                filter: originalCriteriaPayload.filter,
                'post-filter': originalCriteriaPayload['post-filter'],
                aggregations: originalCriteriaPayload.aggregations,
                includes: originalCriteriaPayload.includes,
                fields: originalCriteriaPayload.fields,
                grouping: originalCriteriaPayload.grouping,
                groupFields: originalCriteriaPayload.groupFields,
                associations: originalCriteriaPayload.associations,
                'total-count-mode': 2,
            }),
        );
        expect(usedCriteriaPayload.sort).toBeUndefined();
        expect(searchMock).toHaveBeenCalledWith(usedCriteria, context);
        expect(wrapper.findComponent(MtDataTableStub).props('dataSource')).toEqual(searchResult);
        expect(wrapper.findComponent(MtDataTableStub).props('paginationTotalItems')).toBe(37);
        expect(wrapper.emitted('update-records')).toEqual([
            [
                searchResult,
            ],
        ]);
    });

    it('reloads self-fetching data for page, limit, and search changes', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
                initialPage: 2,
                initialLimit: 25,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__page').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('page-change')).toContainEqual([
            {
                page: 3,
                limit: 25,
            },
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 3,
                limit: 25,
            }),
        );

        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__limit').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('page-change')).toContainEqual([
            {
                page: 1,
                limit: 50,
            },
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                limit: 50,
            }),
        );

        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__search').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('search-change')).toEqual([
            [
                'needle',
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                limit: 50,
                term: 'needle',
            }),
        );
    });

    it('sorts self-fetching data by every dataIndex field with natural sorting', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
                initialPage: 2,
                columns: [
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        dataIndex: 'firstName, lastName',
                        naturalSorting: true,
                    },
                ],
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__sort').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('sort-change')).toEqual([
            [
                {
                    property: 'customerName',
                    dataIndex: 'firstName, lastName',
                    direction: 'DESC',
                    naturalSorting: true,
                },
            ],
        ]);
        expect(wrapper.emitted('page-change')).toEqual([
            [
                {
                    page: 1,
                    limit: 25,
                },
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                sort: [
                    {
                        field: 'firstName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                    {
                        field: 'lastName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                ],
            }),
        );
    });

    it('emits useCustomSort changes without fetching data', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
                columns: [
                    {
                        label: 'Translated name',
                        property: 'translated.name',
                        dataIndex: 'name',
                        useCustomSort: true,
                    },
                ],
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__sort').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('sort-change')).toEqual([
            [
                {
                    property: 'translated.name',
                    dataIndex: 'name',
                    direction: 'DESC',
                    naturalSorting: false,
                },
            ],
        ]);
        expect(getSearchMock(repository)).not.toHaveBeenCalled();
    });

    it('emits table state changes without fetching when data fetching is disabled', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
                disableDataFetching: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__page').trigger('click');
        await wrapper.find('.mt-data-table-stub__limit').trigger('click');
        await wrapper.find('.mt-data-table-stub__search').trigger('click');
        await wrapper.find('.mt-data-table-stub__sort').trigger('click');
        await wrapper.find('.mt-data-table-stub__reload').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.emitted('page-change')).toEqual([
            [
                {
                    page: 3,
                    limit: 25,
                },
            ],
            [
                {
                    page: 1,
                    limit: 50,
                },
            ],
        ]);
        expect(wrapper.emitted('search-change')).toEqual([
            [
                'needle',
            ],
        ]);
        expect(wrapper.emitted('sort-change')).toEqual([
            [
                {
                    property: 'name',
                    dataIndex: 'name',
                    direction: 'DESC',
                    naturalSorting: false,
                },
            ],
        ]);
    });

    it('emits load-failed and keeps previous records when self-fetching fails', async () => {
        const repository = createRepositoryMock();
        const errorResponse = new Error('Failed to load records');
        getSearchMock(repository).mockRejectedValueOnce(errorResponse);
        const wrapper = createWrapperWithoutControlledData({
            props: {
                repository,
            },
        });

        await flushPromises();

        expect(wrapper.findComponent(MtDataTableStub).props('dataSource')).toEqual([]);
        expect(wrapper.findComponent(MtDataTableStub).props('paginationTotalItems')).toBe(0);
        expect(wrapper.emitted('update-records')).toBeUndefined();
        expect(wrapper.emitted('load-failed')).toEqual([
            [
                errorResponse,
            ],
        ]);
    });

    it('keeps controlled mode parent-owned and never fetches', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 2,
                initialLimit: 10,
                isLoading: true,
            },
        });

        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.findComponent(MtDataTableStub).props()).toEqual(
            expect.objectContaining({
                dataSource: records,
                paginationTotalItems: 42,
                currentPage: 2,
                paginationLimit: 10,
                isLoading: true,
            }),
        );

        await wrapper.find('.mt-data-table-stub__sort').trigger('click');
        await wrapper.find('.mt-data-table-stub__page').trigger('click');
        await wrapper.find('.mt-data-table-stub__search').trigger('click');
        await wrapper.find('.mt-data-table-stub__reload').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.emitted('sort-change')).toEqual([
            [
                {
                    property: 'name',
                    dataIndex: 'name',
                    direction: 'DESC',
                    naturalSorting: false,
                },
            ],
        ]);
        expect(wrapper.emitted('page-change')).toEqual([
            [
                {
                    page: 1,
                    limit: 10,
                },
            ],
            [
                {
                    page: 3,
                    limit: 10,
                },
            ],
            [
                {
                    page: 1,
                    limit: 10,
                },
            ],
        ]);
        expect(wrapper.emitted('search-change')).toEqual([
            [
                'needle',
            ],
        ]);
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
