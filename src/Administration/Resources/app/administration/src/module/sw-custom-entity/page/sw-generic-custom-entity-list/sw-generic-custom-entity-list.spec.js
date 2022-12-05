import { shallowMount, config } from '@vue/test-utils';
import swGenericCustomEntityList from 'src/module/sw-custom-entity/page/sw-generic-custom-entity-list';

Shopware.Component.register('sw-generic-custom-entity-list', swGenericCustomEntityList);

const testEntityName = 'custom_test_entity';

const testEntityData = [{
    id: 'some-id',
    title: 'some-title',
    description: 'some-description',
    position: 10,
}];

testEntityData.total = 1;

async function createWrapper(query = {}) {
    config.mocks.$route = {
        params: {
            entityName: testEntityName
        },
        meta: {
            $module: {
                icon: null,
            },
        },
        query,
    };

    return shallowMount(await Shopware.Component.build('sw-generic-custom-entity-list'), {
        provide: {
            customEntityDefinitionService: {
                getDefinitionByName() {
                    return {
                        entity: testEntityName,
                        properties: {},
                        flags: {
                            'admin-ui': {
                                color: 'some-hex-color',
                                listing: {
                                    columns: [{
                                        ref: 'title',
                                    }, {
                                        ref: 'description',
                                        hidden: true
                                    }, {
                                        ref: 'position',
                                    }],
                                },
                            }
                        }
                    };
                }
            },
            repositoryFactory: {
                create(name) {
                    if (name === 'custom_test_entity') {
                        return {
                            entityName: 'custom_test_entity',
                            search: jest.fn(criteria => {
                                testEntityData.criteria = criteria;

                                return testEntityData;
                            })
                        };
                    }

                    throw new Error(`Repository for ${name} is not mocked`);
                }
            },
        },
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="search-bar"/><slot name="smart-bar-header" /><slot name="smart-bar-actions"/><slot name="language-switch" /><slot name="content"/></div>',
            },
            'sw-search-bar': {
                template: '<div class="sw-search-bar"></div>',
                props: [
                    'initial-search-type',
                    'initial-search'
                ]
            },
            'sw-button': {
                template: '<div class="sw-button"></div>',
                props: [
                    'router-link',
                    'variant'
                ]
            },
            'sw-entity-listing': {
                template: '<div class="sw-entity-listing"></div>',
                props: [
                    'repository',
                    'items',
                    'allow-inline-edit',
                    'allow-column-edit',
                    'columns',
                    'sort-by',
                    'sort-direction',
                    'natural-sorting',
                    'criteria-limit',
                    'disable-data-fetching'
                ]
            },
            'sw-empty-state': {
                template: '<div class="sw-empty-state"><slot name="icon"/></div>',
                props: ['title'],
            },
            'sw-language-switch': {
                template: '<div class="sw-language-switch"></div>'
            }
        }
    });
}

/**
 * @package content
 */
describe('module/sw-custom-entity/page/sw-generic-custom-entity-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the empty state when 0 entities are found', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-empty-state').exists()).toBe(false);

        await wrapper.setData({
            customEntityInstances: false
        });

        expect(wrapper.vm.customEntityInstances).toBe(false);

        expect(wrapper.get('.sw-empty-state').props('title')).toBe('custom_test_entity.list.messageEmpty');

        const imageElement = wrapper.get('.sw-empty-state img');

        expect(imageElement.attributes()).toStrictEqual({
            src: 'administration/static/img/empty-states/custom-entity-empty-state.svg',
            alt: 'custom_test_entity.list.messageEmpty'
        });
    });

    it('gets the custom entity definition and renders the columns', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const trimmedTitleText = wrapper.get('.sw-generic-custom-entity-list__title').text().replace(/\s/g, '');
        expect(trimmedTitleText).toContain('custom_test_entity.moduleTitle(1)');

        expect(wrapper.get('.sw-page').attributes('header-border-color')).toBe('some-hex-color');

        const entityListingProps = wrapper.get('.sw-entity-listing').props();
        expect(entityListingProps.repository.entityName).toBe('custom_test_entity');

        expect(entityListingProps.allowInlineEdit).toBe(false);
        expect(entityListingProps.allowColumnEdit).toBe(false);

        expect(entityListingProps.sortBy).toBe('title');
        expect(entityListingProps.sortDirection).toBe('ASC');
        expect(entityListingProps.naturalSorting).toBe(false);
        expect(entityListingProps.criteriaLimit).toBe(25);

        expect(entityListingProps.columns).toStrictEqual([{
            visible: true,
            label: 'custom_test_entity.list.title',
            property: 'title',
            routerLink: 'sw.custom.entity.detail'
        },
        {
            visible: false,
            label: 'custom_test_entity.list.description',
            property: 'description',
            routerLink: 'sw.custom.entity.detail'
        },
        {
            visible: true,
            label: 'custom_test_entity.list.position',
            property: 'position',
            routerLink: 'sw.custom.entity.detail'
        }]);

        const criteriaData = entityListingProps.items.criteria.getCriteriaData();
        expect(criteriaData).toStrictEqual({
            aggregations: [],
            associations: [],
            fields: [],
            filters: [],
            groupFields: [],
            grouping: [],
            ids: [],
            includes: null,
            limit: 25,
            page: 1,
            postFilter: [],
            queries: [],
            sortings: [{
                field: 'title',
                naturalSorting: false,
                order: 'ASC'
            }],
            term: '',
            totalCountMode: 1
        });

        expect(entityListingProps.items).toStrictEqual(testEntityData);
    });

    it('changes to content language with the language switch', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const testLanguageId = 'some-language-id';

        const languageSwitch = wrapper.get('.sw-language-switch');
        languageSwitch.vm.$emit('on-change', testLanguageId);
        expect(Shopware.State.get('context').api.languageId).toBe(testLanguageId);

        const searchMock = wrapper.vm.customEntityRepository.search;
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('parses the route on creation', async () => {
        const wrapper = await createWrapper({
            term: 'some-search-term',
            page: '2',
            limit: '10',
            sortBy: 'position',
            sortDirection: 'ASC',
            naturalSorting: 'false',
        });

        await wrapper.vm.$nextTick();

        wrapper.vm.$options.watch.$route.call(wrapper.vm);

        await wrapper.vm.$nextTick();

        const searchMock = wrapper.vm.customEntityRepository.search;
        expect(searchMock).toHaveBeenCalledTimes(2);


        const entityListingProps = wrapper.get('.sw-entity-listing').props();
        expect(entityListingProps.repository.entityName).toBe('custom_test_entity');

        expect(entityListingProps.allowInlineEdit).toBe(false);
        expect(entityListingProps.allowColumnEdit).toBe(false);

        expect(entityListingProps.sortBy).toBe('position');
        expect(entityListingProps.sortDirection).toBe('ASC');
        expect(entityListingProps.naturalSorting).toBe(false);
        expect(entityListingProps.criteriaLimit).toBe(10);

        const criteriaData = entityListingProps.items.criteria.getCriteriaData();
        expect(criteriaData).toStrictEqual({
            aggregations: [],
            associations: [],
            fields: [],
            filters: [],
            groupFields: [],
            grouping: [],
            ids: [],
            includes: null,
            limit: 10,
            page: 2,
            postFilter: [],
            queries: [],
            sortings: [{
                field: 'position',
                naturalSorting: false,
                order: 'ASC'
            }],
            term: 'some-search-term',
            totalCountMode: 1
        });
    });

    it('changes the route when searching', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const searchBar = wrapper.get('.sw-search-bar');

        expect(searchBar.props('initialSearch')).toBe('');
        expect(searchBar.props('initialSearchType')).toBe(testEntityName);

        searchBar.vm.$emit('search', 'new-search-term');

        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            query: {
                term: 'new-search-term',
                limit: '25',
                page: '1',
                sortBy: 'title',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
    });

    it('changes the route when sorting', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const entityListing = wrapper.get('.sw-entity-listing');

        entityListing.vm.$emit('column-sort', { dataIndex: 'title', naturalSorting: false });

        expect(wrapper.vm.$router.replace).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            query: {
                term: '',
                page: '1',
                limit: '25',
                sortBy: 'title',
                sortDirection: 'DESC',
                naturalSorting: 'false',
            },
        });

        entityListing.vm.$emit('column-sort', { dataIndex: 'position', naturalSorting: false });

        expect(wrapper.vm.$router.replace).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.$router.replace).toHaveBeenLastCalledWith({
            query: {
                term: '',
                page: '1',
                limit: '25',
                sortBy: 'position',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
    });

    it('changes the route when changing the page and limit', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const entityListing = wrapper.get('.sw-entity-listing');

        entityListing.vm.$emit('page-change', { page: 2, limit: 10 });

        expect(wrapper.vm.$router.replace).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            query: {
                term: '',
                page: '2',
                limit: '10',
                sortBy: 'title',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
    });

    it('reacts to route changes and fetches data with new criteria', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        wrapper.vm.$route.query = {
            term: 'some-search-term',
            page: '2',
            limit: '10',
            sortBy: 'position',
            sortDirection: 'ASC',
            naturalSorting: 'false',
        };

        wrapper.vm.$options.watch.$route.call(wrapper.vm);

        await wrapper.vm.$nextTick();

        const searchMock = wrapper.vm.customEntityRepository.search;
        expect(searchMock).toHaveBeenCalledTimes(2);


        const entityListingProps = wrapper.get('.sw-entity-listing').props();
        expect(entityListingProps.repository.entityName).toBe('custom_test_entity');

        expect(entityListingProps.allowInlineEdit).toBe(false);
        expect(entityListingProps.allowColumnEdit).toBe(false);

        expect(entityListingProps.sortBy).toBe('position');
        expect(entityListingProps.sortDirection).toBe('ASC');
        expect(entityListingProps.naturalSorting).toBe(false);
        expect(entityListingProps.criteriaLimit).toBe(10);

        const criteriaData = entityListingProps.items.criteria.getCriteriaData();
        expect(criteriaData).toStrictEqual({
            aggregations: [],
            associations: [],
            fields: [],
            filters: [],
            groupFields: [],
            grouping: [],
            ids: [],
            includes: null,
            limit: 10,
            page: 2,
            postFilter: [],
            queries: [],
            sortings: [{
                field: 'position',
                naturalSorting: false,
                order: 'ASC'
            }],
            term: 'some-search-term',
            totalCountMode: 1
        });
    });
});
