/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

let setSearchTermMock;
let reloadMock;
let repositorySearchMock;

async function createWrapper(privileges = [], repositorySearchResult = []) {
    setSearchTermMock = jest.fn(() => Promise.resolve());
    reloadMock = jest.fn(() => Promise.resolve());
    repositorySearchMock = jest.fn(() => Promise.resolve(repositorySearchResult));

    return mount(await wrapTestComponent('sw-manufacturer-list', { sync: true }), {
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
                'sw-meteor-entity-data-table': {
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
                    template: '<div class="sw-manufacturer-list__grid"></div>',
                },
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
            },
            {
                property: 'link',
                label: 'sw-manufacturer.list.columnLink',
                renderer: 'text',
                sortField: 'link',
            },
        ]);
        expect(meteorTable.attributes('allow-inline-edit')).toBeUndefined();
        expect(meteorTable.attributes('allow-delete')).toBeUndefined();
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
