/**
 * @sw-package inventory
 */

import { flushPromises, mount } from '@vue/test-utils';

import SwMeteorEntityDataTable from 'src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table';

let setSearchTermMock;
let reloadMock;
let repositorySearchMock;

const sortableMtDataTableStub = {
    name: 'mt-data-table',
    props: {
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
    },
    template: `
        <div class="mt-data-table-sort-stub">
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
            'criteria',
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

async function createWrapperWithRealMeteorTable(repositorySearchResult = []) {
    return createWrapper([], repositorySearchResult, 'sw-manufacturer-list', {
        meteorTableComponent: SwMeteorEntityDataTable,
        additionalStubs: {
            'mt-data-table': sortableMtDataTableStub,
            'sw-modal': true,
            'sw-data-grid-inline-edit': true,
            'mt-icon': true,
        },
    });
}

function getMeteorTable(wrapper) {
    return wrapper.getComponent({ name: 'sw-meteor-entity-data-table' });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should have an enabled create button', async () => {
        const wrapper = await createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
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

    it('should delegate smart bar search to the meteor table', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onSearch('ACME');

        expect(wrapper.vm.term).toBe('ACME');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.isLoading).toBe(true);
        expect(setSearchTermMock).toHaveBeenCalledWith('ACME');
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
