/**
 * @sw-package inventory
 */

import { flushPromises, mount } from '@vue/test-utils';

import SwMeteorEntityDataTable from 'src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table';

let setSearchTermMock;
let reloadMock;
let repositorySearchMock;

const { Criteria } = Shopware.Data;

const sortableMtDataTableStub = {
    name: 'mt-data-table',
    props: {
        dataSource: {
            type: Array,
            required: false,
            default: () => [],
        },
        columns: {
            type: Array,
            required: false,
            default: () => [],
        },
    },
    emits: [
        'sort-change',
    ],
    computed: {
        sortableColumns() {
            return this.columns.filter((column) => column.sortable);
        },

        firstRecord() {
            return (
                this.dataSource[0] ?? {
                    id: 'manufacturer-1',
                    name: 'ACME',
                }
            );
        },

        nameColumn() {
            return (
                this.columns.find((column) => column.property === 'name') ?? {
                    property: 'name',
                }
            );
        },
    },
    template: `
        <div class="mt-data-table-sort-stub">
            <slot
                v-if="$slots['column-name']"
                name="column-name"
                :data="firstRecord"
                :column-definition="nameColumn"
            ></slot>

            <button
                v-for="column in sortableColumns"
                :key="column.property"
                class="mt-data-table-sort-stub__sort"
                type="button"
                :data-sort-property="column.property"
                @click="$emit('sort-change', column.property, 'DESC')"
            >
                {{ column.property }}
            </button>
        </div>
    `,
};

async function createWrapper(
    privileges = [],
    repositorySearchResult = [],
    component = 'sw-manufacturer-list',
    options = {},
) {
    setSearchTermMock = jest.fn(() => Promise.resolve());
    reloadMock = jest.fn(() => Promise.resolve());
    repositorySearchMock = jest.fn(() => Promise.resolve(repositorySearchResult));

    const componentConfig = typeof component === 'string' ? await wrapTestComponent(component, { sync: true }) : component;
    const meteorTableComponent = options.meteorTableComponent ?? {
        name: 'sw-meteor-entity-data-table',
        props: [
            'repository',
            'columns',
            'identifier',
            'criteria',
            'criteriaResolver',
            'initialPage',
            'initialLimit',
            'initialSearchTerm',
            'initialSort',
            'layout',
            'detailRoute',
            'searchable',
            'reloadable',
            'selectable',
            'allowEdit',
            'allowInlineEdit',
            'allowDelete',
        ],
        emits: [
            'load-success',
            'load-error',
            'state-change',
        ],
        methods: {
            setSearchTerm(term) {
                return setSearchTermMock(term);
            },
            reload() {
                return reloadMock();
            },
        },
        template: `
            <div class="sw-manufacturer-list__grid">
                <slot
                    name="column-name"
                    v-bind="{
                        data: { id: 'manufacturer-1', name: 'ACME' },
                        columnDefinition: { property: 'name' },
                        item: { id: 'manufacturer-1', name: 'ACME' },
                        column: { property: 'name' }
                    }"
                ></slot>
            </div>
        `,
    };

    return mount(componentConfig, {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="content">CONTENT</slot>
                        </div>
                    `,
                },
                'sw-meteor-entity-data-table': meteorTableComponent,
                ...options.additionalStubs,
                'mt-button': {
                    template: '<button v-bind="$attrs"><slot></slot></button>',
                },
                'mt-empty-state': {
                    props: [
                        'headline',
                    ],
                    template: `
                        <div class="mt-empty-state">
                            <div class="mt-empty-state__headline">{{ headline }}</div>
                        </div>
                    `,
                },
                'sw-loader': true,
                'router-link': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
            },
            directives: {
                tooltip: {},
            },
            provide: {
                acl: {
                    can: (key) => (key ? privileges.includes(key) : true),
                },
                stateStyleDataProviderService: {},
                repositoryFactory: {
                    create: () => ({ search: repositorySearchMock }),
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => {
                        return Promise.resolve({});
                    },
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                        return criteria;
                    },
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            mocks: {
                $route: {
                    name: 'sw.manufacturer.index',
                    query: {},
                    params: {},
                    meta: {
                        $module: {
                            icon: 'solid-content',
                            description: 'sw-manufacturer.general.placeholderSearchBar',
                        },
                    },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                    resolve: jest.fn(() => ({ href: '/search-preferences' })),
                },
            },
        },
    });
}

async function createWrapperWithRealMeteorTable(repositorySearchResult = [], component = 'sw-manufacturer-list') {
    return createWrapper([], repositorySearchResult, component, {
        meteorTableComponent: SwMeteorEntityDataTable,
        additionalStubs: {
            'mt-data-table': sortableMtDataTableStub,
            'sw-modal': true,
            'sw-data-grid-inline-edit': true,
            'sw-media-preview-v2': {
                props: [
                    'source',
                ],
                template: '<span class="sw-media-preview-v2-stub">{{ source }}</span>',
            },
            'mt-icon': true,
        },
    });
}

function getMeteorTable(wrapper) {
    return wrapper.getComponent({ name: 'sw-meteor-entity-data-table' });
}

function getLastRepositorySearchCriteria() {
    const lastCall = repositorySearchMock.mock.calls[repositorySearchMock.mock.calls.length - 1];

    expect(lastCall).toBeDefined();

    return lastCall[0];
}

async function reloadWithRouteState(wrapper, routeState) {
    await wrapper.setData(routeState);
    await wrapper.vm.getList();
    await flushPromises();
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should have an enabled create button', async () => {
        const wrapper = await createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('passes the legacy grid identifier to the Meteor table', async () => {
        const wrapper = await createWrapper();

        expect(getMeteorTable(wrapper).props('identifier')).toBe('sw-manufacturer-list');
    });

    it('should have an disabled create button', async () => {
        const wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should render the meteor entity data table with the new listing props', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        expect(meteorTable.exists()).toBe(true);
        expect(meteorTable.props()).toEqual(
            expect.objectContaining({
                detailRoute: 'sw.manufacturer.detail',
                initialPage: 1,
                initialLimit: 25,
                initialSort: {
                    property: 'name',
                    direction: 'ASC',
                },
                criteriaResolver: expect.any(Function),
                layout: 'full',
                reloadable: true,
                searchable: false,
                selectable: false,
                allowEdit: undefined,
                allowInlineEdit: undefined,
                allowDelete: undefined,
            }),
        );
        expect(meteorTable.props('columns')).toEqual([
            {
                property: 'name',
                label: 'sw-manufacturer.list.columnName',
                renderer: 'text',
                clickable: true,
                previewImage: 'previewMediaUrl',
                sortField: 'name',
                sortable: true,
                inlineEdit: 'string',
            },
            {
                property: 'link',
                label: 'sw-manufacturer.list.columnLink',
                renderer: 'text',
                sortField: 'link',
                sortable: true,
                inlineEdit: 'string',
            },
        ]);
        expect(meteorTable.attributes('allow-inline-edit')).toBeUndefined();
        expect(meteorTable.attributes('allow-delete')).toBeUndefined();
    });

    it('should allow sorting manufacturer columns through the table UI', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
                link: 'https://example.com',
            },
        ]);

        await flushPromises();
        repositorySearchMock.mockClear();

        expect(wrapper.findAll('.mt-data-table-sort-stub__sort')).toHaveLength(2);

        await wrapper.get('[data-sort-property="link"]').trigger('click');
        await flushPromises();

        expect(wrapper.vm.sortBy).toBe('link');
        expect(wrapper.vm.sortDirection).toBe('DESC');
        expect(repositorySearchMock).toHaveBeenCalledTimes(1);
        expect(repositorySearchMock.mock.calls[0][0].parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'link',
                        order: 'DESC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
    });

    it('should keep the manufacturer name column slot extension point', async () => {
        await wrapTestComponent('sw-manufacturer-list', { sync: true });

        Shopware.Component.extend('sw-manufacturer-list-column-slot-extension', 'sw-manufacturer-list', {
            template: `
                {% block sw_manufacturer_list_grid_columns_name_preview %}
                <template #column-name="{ item, column }">
                    <span class="manufacturer-column-slot">{{ item.name }}:{{ column.property }}</span>
                </template>
                {% endblock %}
            `,
        });
        const component = await Shopware.Component.build('sw-manufacturer-list-column-slot-extension');

        component.name += '__wrapped';
        const wrapper = await createWrapper([], [], component);

        expect(wrapper.find('.manufacturer-column-slot').text()).toBe('ACME:name');
    });

    it('should keep the legacy manufacturer preview slot extension point', async () => {
        await wrapTestComponent('sw-manufacturer-list', { sync: true });

        Shopware.Component.extend('sw-manufacturer-list-preview-slot-extension', 'sw-manufacturer-list', {
            template: `
                {% block sw_manufacturer_list_grid_columns_name_preview %}
                <template #preview-name="{ item, column, compact }">
                    <span class="manufacturer-preview-slot">{{ item.name }}:{{ column.property }}:{{ compact }}</span>
                </template>
                {% endblock %}
            `,
        });
        const component = await Shopware.Component.build('sw-manufacturer-list-preview-slot-extension');

        component.name += '__wrapped';
        const wrapper = await createWrapperWithRealMeteorTable(
            [
                {
                    id: 'manufacturer-1',
                    name: 'ACME',
                    media: null,
                },
            ],
            component,
        );

        await flushPromises();

        expect(wrapper.find('.manufacturer-preview-slot').text()).toBe('ACME:name:false');
        expect(wrapper.find('.sw-meteor-entity-data-table__text-renderer').text()).toBe('ACME');
        expect(wrapper.find('.sw-meteor-entity-data-table__preview-image-renderer').exists()).toBe(false);
    });

    it('should render the default manufacturer preview slot', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                mediaId: 'manufacturer-media-id',
                name: 'ACME',
                media: {
                    url: '/media/manufacturer-logo.png',
                },
            },
        ]);

        await flushPromises();

        expect(wrapper.find('.sw-media-preview-v2-stub').text()).toBe('manufacturer-media-id');
        expect(wrapper.find('.sw-meteor-entity-data-table__preview-image-renderer').exists()).toBe(false);
    });

    it('should include the manufacturer media association in the base criteria', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);
        const criteriaPayload = meteorTable.props('criteria').parse();

        expect(criteriaPayload.associations).toHaveProperty('media');
    });

    it('should add a preview image field to manufacturer search results', async () => {
        const manufacturers = [
            {
                id: 'manufacturer-without-media',
                media: null,
            },
            {
                id: 'manufacturer-with-media',
                media: {
                    url: '/media/manufacturer-logo.png',
                },
            },
        ];
        const wrapper = await createWrapper([], manufacturers);
        const meteorTable = getMeteorTable(wrapper);

        const searchResult = await meteorTable.props('repository').search();

        expect(repositorySearchMock).toHaveBeenCalledTimes(1);
        expect(searchResult[0].previewMediaUrl).toContain('data:image/gif;base64');
        expect(searchResult[1].previewMediaUrl).toBe('/media/manufacturer-logo.png');
    });

    it('should allow selection when the user can delete manufacturers', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.deleter',
        ]);
        const meteorTable = getMeteorTable(wrapper);

        expect(meteorTable.props('selectable')).toBe(true);
        expect(meteorTable.props('allowDelete')).toBe(true);
    });

    it('should allow row editing when the user can edit manufacturers', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
        ]);
        const meteorTable = getMeteorTable(wrapper);

        expect(meteorTable.props('allowEdit')).toBe(true);
        expect(meteorTable.props('allowInlineEdit')).toBe(true);
    });

    it('should not allow inline editing when the user cannot edit manufacturers', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        expect(meteorTable.props('allowInlineEdit')).toBeUndefined();
    });

    it('should update total and loading state after the table loads', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        meteorTable.vm.$emit('load-success', { total: 42 });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.total).toBe(42);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(42)');
    });

    it('should clear total and loading state when the table load fails', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        await wrapper.setData({
            total: 42,
        });
        meteorTable.vm.$emit('load-error', { error: new Error('Failed to load records') });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.total).toBe(0);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should synchronize smart bar search with the route', async () => {
        const wrapper = await createWrapper();

        await flushPromises();
        wrapper.vm.$router.replace.mockClear();
        wrapper.vm.$router.push.mockClear();

        await wrapper.vm.onSearch('ACME');

        expect(wrapper.vm.term).toBe('ACME');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.isLoading).toBe(true);
        expect(setSearchTermMock).not.toHaveBeenCalled();
        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                limit: 25,
                page: 1,
                term: 'ACME',
                sortBy: 'name',
                sortDirection: 'ASC',
                naturalSorting: false,
            },
        });
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });

    it('should add query scores to manufacturer searches without Admin OpenSearch', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
            },
        ]);
        await flushPromises();
        repositorySearchMock.mockClear();

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
            return criteria;
        });
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await reloadWithRouteState(wrapper, {
            term: 'ACME',
            page: 1,
        });

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledWith('product_manufacturer');
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledWith(
            { name: 500 },
            'ACME',
            expect.any(Criteria),
        );
    });

    it('should not request search ranking fields without a valid search term', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
            },
        ]);
        await flushPromises();
        repositorySearchMock.mockClear();

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
            return criteria;
        });
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
        expect(repositorySearchMock).toHaveBeenCalledTimes(1);
    });

    it('should not search manufacturers when no searchable fields are configured', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
            },
        ]);
        await flushPromises();
        repositorySearchMock.mockClear();

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
            return criteria;
        });
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await reloadWithRouteState(wrapper, {
            term: 'ACME',
            page: 1,
        });

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
        expect(repositorySearchMock).not.toHaveBeenCalled();
        expect(wrapper.vm.entitySearchable).toBe(false);
        expect(wrapper.vm.total).toBe(0);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-manufacturer-list__grid').exists()).toBe(false);
    });

    it('should reset active sorting for fresh ranked manufacturer searches', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
            },
        ]);
        await flushPromises();
        repositorySearchMock.mockClear();

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
            return criteria;
        });
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await reloadWithRouteState(wrapper, {
            term: 'ACME',
            page: 1,
        });

        expect(getLastRepositorySearchCriteria().parse().sort).toBeUndefined();
    });

    it('should use the criteria term without ranked queries when Admin OpenSearch is enabled', async () => {
        const previousAdminEsEnable = Shopware.Context.app.adminEsEnable;

        try {
            global.activeFeatureFlags = [
                'ENABLE_OPENSEARCH_FOR_ADMIN_API',
            ];
            Shopware.Context.app.adminEsEnable = true;

            const wrapper = await createWrapperWithRealMeteorTable([
                {
                    id: 'manufacturer-1',
                    name: 'ACME',
                },
            ]);
            await flushPromises();
            repositorySearchMock.mockClear();

            wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
                return criteria;
            });
            wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
                return { name: 500 };
            });

            await reloadWithRouteState(wrapper, {
                term: 'ACME',
                page: 1,
            });

            expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();
            expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
            expect(getLastRepositorySearchCriteria().parse()).toEqual(
                expect.objectContaining({
                    term: 'ACME',
                }),
            );
        } finally {
            Shopware.Context.app.adminEsEnable = previousAdminEsEnable;
            global.activeFeatureFlags = [];
        }
    });

    it('should reload the meteor table from the compatibility getList method', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.getList();

        expect(wrapper.vm.isLoading).toBe(true);
        expect(reloadMock).toHaveBeenCalledTimes(1);
    });

    it('should mirror meteor table state changes into the listing state', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        await flushPromises();
        wrapper.vm.$router.replace.mockClear();
        wrapper.vm.$router.push.mockClear();

        meteorTable.vm.$emit('state-change', {
            page: 3,
            limit: 50,
            searchTerm: 'ACME',
            sort: {
                property: 'link',
                direction: 'DESC',
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.page).toBe(3);
        expect(wrapper.vm.limit).toBe(50);
        expect(wrapper.vm.term).toBe('ACME');
        expect(wrapper.vm.sortBy).toBe('link');
        expect(wrapper.vm.sortDirection).toBe('DESC');
        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                limit: 50,
                page: 3,
                term: 'ACME',
                sortBy: 'link',
                sortDirection: 'DESC',
                naturalSorting: false,
            },
        });
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });

    it('should reload the meteor table with route-derived listing state after mount', async () => {
        const wrapper = await createWrapperWithRealMeteorTable([
            {
                id: 'manufacturer-1',
                name: 'ACME',
            },
        ]);
        await flushPromises();
        repositorySearchMock.mockClear();

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn((searchFields, term, criteria) => {
            return criteria;
        });
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await reloadWithRouteState(wrapper, {
            page: 3,
            limit: 50,
            term: 'ACME',
        });

        expect(repositorySearchMock).toHaveBeenCalledTimes(1);
        expect(getLastRepositorySearchCriteria().parse()).toEqual(
            expect.objectContaining({
                page: 3,
                limit: 50,
                term: 'ACME',
            }),
        );
    });

    it('should show empty state when the meteor table reports no records after search', async () => {
        const wrapper = await createWrapper();
        const meteorTable = getMeteorTable(wrapper);

        await wrapper.setData({
            term: 'ACME',
        });
        meteorTable.vm.$emit('load-success', { total: 0 });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.sw-manufacturer-list__grid').exists()).toBe(false);
    });
});
