/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import {
    SwMeteorEntityDataTable,
    createDeferred,
    createSearchResult,
    createWrapper,
    firstSearchCriteria,
    lastSearchCriteria,
    mountedTable,
    type TestWrapper,
} from './sw-meteor-entity-data-table.test-utils';

describe('src/app/component/entity/sw-meteor-entity-data-table', () => {
    afterEach(() => {
        _overridesMap['sw-meteor-entity-data-table']?.splice(0);
    });

    it('renders mt-data-table with loaded repository records', async () => {
        const wrapper = await createWrapper();
        const dataTable = mountedTable(wrapper);

        expect(dataTable.props('dataSource')).toEqual([
            { id: 'manufacturer-1', name: 'Shopware' },
            { id: 'manufacturer-2', name: 'Meteor' },
        ]);
        expect(dataTable.props('paginationTotalItems')).toBe(42);
    });

    it('renders mt-data-table with the full layout', async () => {
        const wrapper = await createWrapper();

        expect(mountedTable(wrapper).props('layout')).toBe('full');
    });

    it('emits load-success with records, total, and state', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted('load-success')).toHaveLength(1);
        expect(wrapper.emitted('load-success')?.[0]?.[0]).toEqual({
            records: [
                { id: 'manufacturer-1', name: 'Shopware' },
                { id: 'manufacturer-2', name: 'Meteor' },
            ],
            total: 42,
            state: {
                page: 1,
                limit: 25,
                searchTerm: '',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: false,
            },
        });
    });

    it('exposes records, total, loading, and state on the component instance', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.records).toEqual([
            { id: 'manufacturer-1', name: 'Shopware' },
            { id: 'manufacturer-2', name: 'Meteor' },
        ]);
        expect(wrapper.vm.total).toBe(42);
        expect(wrapper.vm.loading).toBe(false);
        expect(wrapper.vm.state).toEqual({
            page: 1,
            limit: 25,
            searchTerm: '',
            sortBy: '',
            sortDirection: 'ASC',
            naturalSorting: false,
        });
    });

    it('renders loading state while repository search is pending', async () => {
        const searchDeferred = createDeferred<ReturnType<typeof createSearchResult>>();
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() => searchDeferred.promise),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: ['isLoading'],
                        template: '<div class="mt-data-table-stub"></div>',
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.loading).toBe(true);
        expect(mountedTable(wrapper).props('isLoading')).toBe(true);

        searchDeferred.resolve(createSearchResult([{ id: 'manufacturer-1', name: 'Shopware' }], 1));
        await flushPromises();

        expect(wrapper.vm.loading).toBe(false);
    });

    it('emits load-error and clears loading state when repository search rejects', async () => {
        const error = new Error('Search failed');
        const wrapper = await createWrapper({
            repository: {
                search: jest.fn(() => Promise.reject(error)),
            },
        });

        expect(wrapper.vm.loading).toBe(false);
        expect(wrapper.emitted('load-error')).toEqual([[error]]);
    });

    it('ignores stale repository results when a newer load has already started', async () => {
        const firstSearch = createDeferred<ReturnType<typeof createSearchResult>>();
        const secondSearch = createDeferred<ReturnType<typeof createSearchResult>>();
        const repository = {
            search: jest.fn().mockReturnValueOnce(firstSearch.promise).mockReturnValueOnce(secondSearch.promise),
        };

        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository,
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: ['dataSource'],
                        template: '<div class="mt-data-table-stub"></div>',
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await wrapper.vm.$nextTick();
        const reloadPromise = wrapper.vm.reload();

        secondSearch.resolve(createSearchResult([{ id: 'manufacturer-2', name: 'Meteor' }], 1));
        await reloadPromise;
        await flushPromises();

        firstSearch.resolve(createSearchResult([{ id: 'manufacturer-1', name: 'Shopware' }], 1));
        await flushPromises();

        expect(wrapper.vm.records).toEqual([{ id: 'manufacturer-2', name: 'Meteor' }]);
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.emitted('load-success')).toHaveLength(1);
    });

    it('maps simple text columns to mt-data-table column definitions', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
            ],
        });

        expect(mountedTable(wrapper).props('columns')).toEqual([
            expect.objectContaining({
                property: 'name',
                label: 'Name',
                renderer: 'text',
            }),
        ]);
    });

    it('passes mutable column changes to mt-data-table settings', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    primary: true,
                },
                {
                    property: 'link',
                    label: 'Link',
                },
            ],
        });

        const columnChanges = mountedTable(wrapper).props('columnChanges') as Record<string, { visible?: boolean }>;

        expect(columnChanges).toEqual({});

        columnChanges.link = {
            visible: false,
        };

        await wrapper.vm.$nextTick();

        expect(mountedTable(wrapper).props('columnChanges')).toEqual({
            link: {
                visible: false,
            },
        });
    });

    it('updates controlled mt-data-table view settings from settings events', async () => {
        const wrapper = await createWrapper();
        const dataTable = mountedTable(wrapper);

        expect(dataTable.props('enableRowNumbering')).toBe(false);
        expect(dataTable.props('showStripes')).toBe(true);
        expect(dataTable.props('showOutlines')).toBe(true);
        expect(dataTable.props('enableOutlineFraming')).toBe(false);

        await dataTable.vm.$emit('change-enable-row-numbering', true);
        await dataTable.vm.$emit('change-show-stripes', false);
        await dataTable.vm.$emit('change-show-outlines', false);
        await dataTable.vm.$emit('change-outline-framing', true);
        await wrapper.vm.$nextTick();

        expect(mountedTable(wrapper).props('enableRowNumbering')).toBe(true);
        expect(mountedTable(wrapper).props('showStripes')).toBe(false);
        expect(mountedTable(wrapper).props('showOutlines')).toBe(false);
        expect(mountedTable(wrapper).props('enableOutlineFraming')).toBe(true);
    });

    it('assigns deterministic position values when columns have none', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
                {
                    property: 'link',
                    label: 'Link',
                },
            ],
        });

        expect(wrapper.vm.resolvedColumns.map((column) => column.position)).toEqual([
            100,
            200,
        ]);
    });

    it('translates column labels before passing them to mt-data-table', async () => {
        const wrapper = await createWrapper(
            {
                columns: [
                    {
                        property: 'name',
                        label: 'sw-manufacturer.list.columnName',
                    },
                ],
            },
            {
                globalProperties: {
                    $t: (key: string) => `translated: ${key}`,
                },
            },
        );

        expect(wrapper.vm.resolvedColumns[0].label).toBe('translated: sw-manufacturer.list.columnName');
    });

    it('keeps display property separate from sort-only fields without leaking wrapper-only fields', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'displayName',
                    dataIndex: 'name',
                    sortField: 'translated.name',
                    label: 'Name',
                },
            ],
        });

        expect(wrapper.vm.resolvedColumns[0]).toEqual(
            expect.objectContaining({
                property: 'displayName',
            }),
        );
        expect(wrapper.vm.resolvedColumns[0]).not.toHaveProperty('dataIndex');
        expect(wrapper.vm.resolvedColumns[0]).not.toHaveProperty('sortField');
    });

    it('maps routerLink and primary columns to Meteor click behavior', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    routerLink: 'sw.manufacturer.detail',
                    primary: true,
                },
            ],
        });

        expect(wrapper.vm.resolvedColumns[0]).toEqual(
            expect.objectContaining({
                clickable: true,
            }),
        );
    });

    it('keeps supported column presentation options', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    visible: false,
                    width: 220,
                    sortable: false,
                    allowResize: false,
                },
            ],
        });

        expect(wrapper.vm.resolvedColumns[0]).toEqual(
            expect.objectContaining({
                visible: false,
                width: 220,
                sortable: false,
                allowResize: false,
            }),
        );
    });

    it('normalizes unsupported width values deliberately', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    width: 'auto',
                },
            ],
        });

        expect(wrapper.vm.resolvedColumns[0]).not.toHaveProperty('width');
    });

    it('renders the preview fallback when a preview image value is missing', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    previewImage: 'media.url',
                },
            ],
        });

        expect(wrapper.get('.sw-meteor-entity-data-table__preview-image').attributes('src')).toBe(
            'administration/administration/static/img/empty-states/media-empty-state.svg',
        );
    });

    it('routes preview fallback assets through the configured assets path', async () => {
        const previousAssetsPath = Shopware.Context.api.assetsPath;
        Shopware.Context.api.assetsPath = 'https://cdn.example.test/';

        try {
            const wrapper = await createWrapper({
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        previewImage: 'media.url',
                    },
                ],
            });

            expect(wrapper.get('.sw-meteor-entity-data-table__preview-image').attributes('src')).toBe(
                'https://cdn.example.test/administration/administration/static/img/empty-states/media-empty-state.svg',
            );
        } finally {
            Shopware.Context.api.assetsPath = previousAssetsPath;
        }
    });

    it('keeps real media preview URLs unchanged when an assets path is configured', async () => {
        const previousAssetsPath = Shopware.Context.api.assetsPath;
        const mediaUrl = 'https://media.example.test/manufacturer.png';
        Shopware.Context.api.assetsPath = 'https://cdn.example.test/';

        try {
            const wrapper = await createWrapper({
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                {
                                    id: 'manufacturer-1',
                                    name: 'Shopware',
                                    media: {
                                        url: mediaUrl,
                                    },
                                },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        previewImage: 'media.url',
                    },
                ],
            });

            expect(wrapper.get('.sw-meteor-entity-data-table__preview-image').attributes('src')).toBe(mediaUrl);
        } finally {
            Shopware.Context.api.assetsPath = previousAssetsPath;
        }
    });

    it('creates criteria from page, limit, search term, and sort state', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };

        await createWrapper({
            repository,
            initialPage: 3,
            initialLimit: 50,
            initialSearchTerm: 'shop',
            initialSortBy: 'name',
            initialSortDirection: 'DESC',
            initialNaturalSorting: true,
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
            ],
        });

        const criteria = firstSearchCriteria(repository);

        expect(criteria.page).toBe(3);
        expect(criteria.limit).toBe(50);
        expect(criteria.term).toBe('shop');
        expect(criteria.sortings).toEqual([
            {
                field: 'name',
                order: 'DESC',
                naturalSorting: true,
            },
        ]);
    });

    it('clones a passed base criteria instead of mutating the prop', async () => {
        const baseCriteria = new Criteria(2, 25);
        baseCriteria.addSorting(Criteria.sort('createdAt', 'DESC', false));

        await createWrapper({
            criteria: baseCriteria,
            initialPage: 4,
            initialLimit: 10,
            initialSortBy: 'name',
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
            ],
        });

        expect(baseCriteria.page).toBe(2);
        expect(baseCriteria.limit).toBe(25);
        expect(baseCriteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);
    });

    it('applies sort fields from the active column', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };

        await createWrapper({
            repository,
            initialSortBy: 'displayName',
            columns: [
                {
                    property: 'displayName',
                    dataIndex: 'translated.name',
                    label: 'Name',
                },
            ],
        });

        expect(firstSearchCriteria(repository).sortings).toEqual([
            {
                field: 'translated.name',
                order: 'ASC',
                naturalSorting: false,
            },
        ]);
    });

    it('supports multiple sort fields', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };

        await createWrapper({
            repository,
            initialSortBy: 'name',
            columns: [
                {
                    property: 'name',
                    dataIndex: 'translated.name, name',
                    label: 'Name',
                },
            ],
        });

        expect(firstSearchCriteria(repository).sortings).toEqual([
            {
                field: 'translated.name',
                order: 'ASC',
                naturalSorting: false,
            },
            {
                field: 'name',
                order: 'ASC',
                naturalSorting: false,
            },
        ]);
    });

    it('calls criteriaResolver before repository search', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const criteriaResolver = jest.fn((criteria: Criteria) => {
            criteria.setLimit(5);

            return criteria;
        });

        await createWrapper({
            repository,
            criteriaResolver,
        });

        expect(criteriaResolver).toHaveBeenCalledTimes(1);
        expect(firstSearchCriteria(repository).limit).toBe(5);
    });

    it('stops loading and returns an empty result when criteriaResolver returns null', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([{ id: 'manufacturer-1' }]))),
        };

        const wrapper = await createWrapper({
            repository,
            criteriaResolver: () => null,
        });

        expect(repository.search).not.toHaveBeenCalled();
        expect(wrapper.vm.records).toEqual([]);
        expect(wrapper.vm.total).toBe(0);
        expect(wrapper.vm.loading).toBe(false);
    });

    it('pagination-current-page-change updates state, emits page-change, and reloads', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({ repository });

        await mountedTable(wrapper).vm.$emit('pagination-current-page-change', 3);
        await flushPromises();

        expect(wrapper.vm.state.page).toBe(3);
        expect(wrapper.emitted('page-change')?.at(-1)?.[0]).toEqual({
            page: 3,
            limit: 25,
        });
        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(lastSearchCriteria(repository).page).toBe(3);
    });

    it('pagination-limit-change resets page, emits page-change, and reloads', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 4,
        });

        await mountedTable(wrapper).vm.$emit('pagination-limit-change', 50);
        await flushPromises();

        expect(wrapper.vm.state.page).toBe(1);
        expect(wrapper.vm.state.limit).toBe(50);
        expect(wrapper.emitted('page-change')?.at(-1)?.[0]).toEqual({
            page: 1,
            limit: 50,
        });
        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(lastSearchCriteria(repository).page).toBe(1);
        expect(lastSearchCriteria(repository).limit).toBe(50);
    });

    it('sort-change resets page, emits wrapper column-sort, and reloads', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 2,
            columns: [
                {
                    property: 'name',
                    dataIndex: 'translated.name',
                    label: 'Name',
                    naturalSorting: true,
                },
            ],
        });

        await mountedTable(wrapper).vm.$emit('sort-change', 'name', 'DESC');
        await flushPromises();

        expect(wrapper.vm.state.page).toBe(1);
        expect(wrapper.vm.state.sortBy).toBe('name');
        expect(wrapper.vm.state.sortDirection).toBe('DESC');
        const sortChangePayload = wrapper.emitted('column-sort')?.at(-1) as [
            {
                property?: string;
                dataIndex?: string;
            },
            'ASC' | 'DESC',
        ];

        expect(sortChangePayload[0]).toEqual(
            expect.objectContaining({
                property: 'name',
                dataIndex: 'translated.name',
            }),
        );
        expect(sortChangePayload[1]).toBe('DESC');
        expect(lastSearchCriteria(repository).sortings).toEqual([
            {
                field: 'translated.name',
                order: 'DESC',
                naturalSorting: true,
            },
        ]);
    });
    it('search-value-change resets page, emits wrapper search event, and reloads', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 2,
        });

        await mountedTable(wrapper).vm.$emit('search-value-change', 'new search');
        await flushPromises();

        expect(wrapper.vm.state.page).toBe(1);
        expect(wrapper.vm.state.searchTerm).toBe('new search');
        expect(wrapper.emitted('update:searchTerm')?.at(-1)?.[0]).toBe('new search');
        expect(wrapper.emitted('search-term-change')?.at(-1)?.[0]).toBe('new search');
        expect(wrapper.emitted('search-value-change')?.at(-1)?.[0]).toBe('new search');
        expect(lastSearchCriteria(repository).term).toBe('new search');
    });

    it('reload reloads with the current state', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 2,
            initialLimit: 50,
            initialSearchTerm: 'current',
        });

        await mountedTable(wrapper).vm.$emit('reload');
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(lastSearchCriteria(repository).page).toBe(2);
        expect(lastSearchCriteria(repository).limit).toBe(50);
        expect(lastSearchCriteria(repository).term).toBe('current');
    });

    it('prop changes for initial state resync wrapper state without double loading', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 1,
            initialLimit: 25,
            initialSearchTerm: '',
            initialSortBy: '',
        });

        await wrapper.setProps({
            initialPage: 3,
            initialLimit: 50,
            initialSearchTerm: 'route term',
            initialSortBy: 'name',
            initialSortDirection: 'DESC',
        });
        await flushPromises();

        expect(wrapper.vm.state).toEqual(
            expect.objectContaining({
                page: 3,
                limit: 50,
                searchTerm: 'route term',
                sortBy: 'name',
                sortDirection: 'DESC',
            }),
        );
        expect(repository.search).toHaveBeenCalledTimes(1);
    });

    it('forwards column-property slots with Meteor scope values only', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            slots: {
                'column-name': `
                    <template #default="{ data, columnDefinition, item, column, columnIndex }">
                        <span class="meteor-column-slot">
                            {{ data?.name }}|{{ columnDefinition?.property }}|{{ item === undefined }}|{{ column === undefined }}|{{ columnIndex === undefined }}
                        </span>
                    </template>
                `,
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: [
                            'dataSource',
                            'columns',
                        ],
                        template: `
                            <div class="mt-data-table-stub">
                                <slot
                                    name="column-name"
                                    :data="dataSource[0]"
                                    :column-definition="columns[0]"
                                />
                            </div>
                        `,
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();

        expect(wrapper.get('.meteor-column-slot').text()).toBe('Shopware|name|true|true|true');
    });

    it('forwards preview-property slots with Meteor scope values', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                {
                                    id: 'manufacturer-1',
                                    name: 'Shopware',
                                    mediaId: 'media-1',
                                },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            slots: {
                'preview-name': `
                    <template #default="{ data }">
                        <span class="meteor-preview-slot">{{ data?.mediaId }}</span>
                    </template>
                `,
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: [
                            'dataSource',
                            'columns',
                        ],
                        template: `
                            <div class="mt-data-table-stub">
                                <slot
                                    name="column-name"
                                    :data="dataSource[0]"
                                    :column-definition="columns[0]"
                                />
                            </div>
                        `,
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();

        expect(wrapper.get('.meteor-preview-slot').text()).toBe('media-1');
        expect(wrapper.text()).toContain('Shopware');
    });

    it('does not forward internal-only slots twice', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            slots: {
                default: '<span class="default-slot">Default</span>',
                'preview-name': '<span class="preview-slot">Preview</span>',
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: [
                            'dataSource',
                            'columns',
                        ],
                        template: `
                            <div class="mt-data-table-stub">
                                <slot
                                    name="column-name"
                                    :data="dataSource[0]"
                                    :column-definition="columns[0]"
                                />
                                <slot name="default" />
                            </div>
                        `,
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();

        expect(wrapper.find('.default-slot').exists()).toBe(false);
        expect(wrapper.findAll('.preview-slot')).toHaveLength(1);
    });
});
