import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product-stream/component/sw-product-stream-modal-preview';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search-ids/sales-channel',
    status: 200,
    response: {
        data: []
    }
});

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-stream-modal-preview'), {
        stubs: {
            'sw-modal': true,
            'sw-label': true,
            'sw-simple-search-field': true,
            'sw-container': true,
            'sw-entity-single-select': true
        },
        provide: {
            productStreamPreviewService: {}
        },
        propsData: {
            filters: [
                {
                    productStreamId: '7a1abc58b4a641a1a0f723962b302a91',
                    parentId: null,
                    type: 'multi',
                    field: null,
                    operator: 'OR',
                    value: null,
                    parameters: null,
                    position: 0,
                    customFields: null,
                    createdAt: '2021-11-19T09:34:39.497+00:00',
                    updatedAt: '2021-11-23T11:31:16.984+00:00',
                    apiAlias: null,
                    id: '75a72e9922c04f5d846ecda1cc334c8a',
                    queries: [
                        {
                            productStreamId: '7a1abc58b4a641a1a0f723962b302a91',
                            parentId: '75a72e9922c04f5d846ecda1cc334c8a',
                            type: 'multi',
                            field: null,
                            operator: 'AND',
                            value: null,
                            parameters: null,
                            position: 0,
                            customFields: null,
                            createdAt: '2021-11-19T09:34:39.497+00:00',
                            updatedAt: '2021-11-23T11:31:16.984+00:00',
                            apiAlias: null,
                            id: 'c0a1b051ada543f69fb57f2adcc2334c',
                            queries: [
                                {
                                    productStreamId: '7a1abc58b4a641a1a0f723962b302a91',
                                    parentId: 'c0a1b051ada543f69fb57f2adcc2334c',
                                    type: 'equals',
                                    field: 'categoriesRo.id',
                                    operator: null,
                                    value: 'cb0a326d9e8b44cea2e385945fbb50c6',
                                    parameters: null,
                                    position: 0,
                                    customFields: null,
                                    createdAt: '2021-11-19T09:34:39.497+00:00',
                                    updatedAt: '2021-11-23T11:31:16.984+00:00',
                                    apiAlias: null,
                                    id: 'f301f6a0755f47bda70e77b58848e135',
                                    queries: []
                                },
                                {
                                    productStreamId: '7a1abc58b4a641a1a0f723962b302a91',
                                    parentId: 'c0a1b051ada543f69fb57f2adcc2334c',
                                    type: 'equalsAny',
                                    field: 'id',
                                    operator: null,
                                    value: 'afb5473d92334cf0b9826d544837fc3d',
                                    parameters: null,
                                    position: 1,
                                    customFields: null,
                                    createdAt: '2021-11-22T06:45:48.077+00:00',
                                    updatedAt: '2021-11-23T11:31:16.984+00:00',
                                    apiAlias: null,
                                    id: '8615ea9f816648a6bc766e790823d8cc',
                                    queries: []
                                }
                            ]
                        }
                    ]
                }
            ]
        }
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-modal-preview', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should map filters for search', () => {
        const wrapper = createWrapper();

        const expected = [
            {
                field: null,
                type: 'multi',
                operator: 'OR',
                value: null,
                parameters: null,
                queries: [
                    {
                        field: null,
                        type: 'multi',
                        operator: 'AND',
                        value: null,
                        parameters: null,
                        queries: [
                            {
                                field: 'categoriesRo.id',
                                type: 'equals',
                                operator: null,
                                value: 'cb0a326d9e8b44cea2e385945fbb50c6',
                                parameters: null,
                                queries: []
                            },
                            {
                                type: 'multi',
                                field: null,
                                operator: 'OR',
                                value: null,
                                parameters: null,
                                queries: [
                                    {
                                        type: 'equalsAny',
                                        field: 'id',
                                        operator: null,
                                        value: 'afb5473d92334cf0b9826d544837fc3d',
                                        parameters: null,
                                        queries: []
                                    },
                                    {
                                        type: 'equalsAny',
                                        field: 'parentId',
                                        operator: null,
                                        value: 'afb5473d92334cf0b9826d544837fc3d',
                                        parameters: null,
                                        queries: []
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ];

        const mappedFilters = wrapper.vm.mapFiltersForSearch(wrapper.vm.filters);

        expect(mappedFilters).toEqual(expected);
    });
});
