/**
 * @package services-settings
 */

import { mount } from '@vue/test-utils';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search-ids/sales-channel',
    status: 200,
    response: {
        data: [],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-stream-modal-preview', { sync: true }), {
        props: {
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
                                    queries: [],
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
                                    queries: [],
                                },
                            ],
                        },
                    ],
                },
            ],
        },
        global: {
            stubs: {
                'sw-modal': true,
                'sw-label': true,
                'sw-simple-search-field': true,
                'sw-container': true,
                'sw-entity-single-select': true,
                'sw-empty-state': true,
                'sw-product-variant-info': true,
                'router-link': true,
                'sw-data-grid-column-boolean': true,
                'sw-color-badge': true,
                'sw-pagination': true,
                'sw-data-grid': true,
                'sw-button': true,
            },
            provide: {
                productStreamPreviewService: {},
            },
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-modal-preview', () => {
    it('should map filters for search', async () => {
        const wrapper = await createWrapper();

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
                                queries: [],
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
                                        queries: [],
                                    },
                                    {
                                        type: 'equalsAny',
                                        field: 'parentId',
                                        operator: null,
                                        value: 'afb5473d92334cf0b9826d544837fc3d',
                                        parameters: null,
                                        queries: [],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ];

        const mappedFilters = wrapper.vm.mapFiltersForSearch(wrapper.vm.filters);

        expect(mappedFilters).toEqual(expected);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.currencyFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.stockColorVariantFilter).toEqual(expect.any(Function));
    });

    it('should load sales channel successfully', async () => {
        const wrapper = await createWrapper();
        const salesChannelData = {
            id: '1',
            name: 'Sales Channel 1',
            currency: {
                id: '1',
                isoCode: 'PLN',
            },
            currencyId: '2',
        };
        wrapper.vm.selectedSalesChannel = '1';

        jest.spyOn(wrapper.vm.salesChannelRepository, 'get').mockImplementation(() => {
            return Promise.resolve(salesChannelData);
        });

        await wrapper.vm.loadSalesChannelById();

        expect(wrapper.vm.salesChannelRepository.get).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.selectedCurrencyIsoCode).toEqual(salesChannelData.currency.isoCode);
        expect(wrapper.vm.selectedCurrencyId).toEqual(salesChannelData.currencyId);
    });

    it('should not load sales channel', async () => {
        const wrapper = await createWrapper();

        jest.spyOn(wrapper.vm.salesChannelRepository, 'get').mockImplementation(() => {
            return Promise.resolve({});
        });

        await wrapper.vm.loadSalesChannelById();

        expect(wrapper.vm.salesChannelRepository.get).toHaveBeenCalledTimes(0);
    });

    it('should map filters for search with rule Is not equal to any of', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
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
                                    type: 'not',
                                    field: null,
                                    operator: null,
                                    value: null,
                                    parameters: null,
                                    position: 0,
                                    customFields: null,
                                    createdAt: '2021-11-19T09:34:39.497+00:00',
                                    updatedAt: '2021-11-23T11:31:16.984+00:00',
                                    apiAlias: null,
                                    id: 'f301f6a0755f47bda70e77b58848e135',
                                    queries: [
                                        {
                                            productStreamId: '7a1abc58b4a641a1a0f723962b302a91',
                                            parentId: 'f301f6a0755f47bda70e77b58848e135',
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
                                            id: '0189de3825ae719d9a08eeea48d6e13a',
                                            queries: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        });

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
                                field: null,
                                type: 'not',
                                operator: null,
                                value: null,
                                parameters: null,
                                queries: [
                                    {
                                        type: 'multi',
                                        field: null,
                                        operator: 'AND',
                                        value: null,
                                        parameters: null,
                                        queries: [
                                            {
                                                type: 'equalsAny',
                                                field: 'id',
                                                operator: null,
                                                value: 'afb5473d92334cf0b9826d544837fc3d',
                                                parameters: null,
                                                queries: [],
                                            },
                                            {
                                                type: 'equalsAny',
                                                field: 'parentId',
                                                operator: null,
                                                value: 'afb5473d92334cf0b9826d544837fc3d',
                                                parameters: null,
                                                queries: [],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ];

        const mappedFilters = wrapper.vm.mapFiltersForSearch(wrapper.vm.filters);

        expect(mappedFilters).toEqual(expected);
    });
});
