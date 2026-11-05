import { mount } from '@vue/test-utils';
import type CriteriaType from 'src/core/data/criteria.data';

/**
 * @sw-package inventory
 */

type SalesChannel = { id: string; name: string };
type NewProduct = { id: string; visibilities: Array<{ salesChannelId: string }>; isNew: () => boolean };

function createSalesChannels(count: number): SalesChannel[] {
    return Array.from({ length: count }, (_, index) => ({
        id: `salesChannel${index}`,
        name: `Sales channel ${index}`,
    }));
}

/**
 * Answers a search like the API: a request by ids returns every record with those ids,
 * any other request returns one page of the records its `equalsAny` filters on `id` match.
 */
function searchSalesChannels(salesChannels: SalesChannel[], criteria: CriteriaType): Promise<SalesChannel[]> {
    if (criteria.ids.length > 0) {
        return Promise.resolve(salesChannels.filter(({ id }) => criteria.ids.includes(id)));
    }

    const matching = salesChannels.filter(({ id }) =>
        criteria.filters.every(
            (filter) => filter.type !== 'equalsAny' || filter.field !== 'id' || filter.value.includes(id),
        ),
    );

    return Promise.resolve(criteria.limit ? matching.slice(0, criteria.limit) : matching);
}

async function mountNewProduct(defaultSalesChannels: SalesChannel[]): Promise<NewProduct> {
    const product: NewProduct = { id: 'newProduct', visibilities: [], isNew: () => true };

    mount(await wrapTestComponent('sw-product-detail', { sync: true }), {
        global: {
            mocks: {
                $route: { name: 'sw.product.create.base', params: {} },
            },
            provide: {
                numberRangeService: { reserve: () => Promise.resolve({ number: 1 }) },
                seoUrlService: {},
                mediaService: {},
                repositoryFactory: {
                    create: (entity: string) => ({
                        create: () => (entity === 'product' ? product : {}),
                        search: (criteria: CriteriaType) =>
                            entity === 'sales_channel'
                                ? searchSalesChannels(defaultSalesChannels, criteria)
                                : Promise.resolve([]),
                        searchIds: () => Promise.resolve({ data: [] }),
                    }),
                },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve({}),
                    getValues: () =>
                        Promise.resolve({
                            'core.defaultSalesChannel.active': true,
                            'core.defaultSalesChannel.salesChannel': defaultSalesChannels.map(({ id }) => id),
                            'core.defaultSalesChannel.visibility': {},
                        }),
                },
                customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },
                entityValidationService: {},
            },
            stubs: {
                'sw-page': true,
                'router-view': true,
            },
        },
    });

    await flushPromises();

    return product;
}

describe('sw-product-detail', () => {
    beforeEach(() => {
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
    });

    /**
     * @regression #22244 A new product was visible in only the first 25 of the sales channels configured as defaults.
     */
    it('makes a new product visible in every default sales channel, also beyond 25', async () => {
        // ARRANGE
        const defaultSalesChannels = createSalesChannels(30);

        // ACT
        const product = await mountNewProduct(defaultSalesChannels);

        // ASSERT
        expect(product.visibilities.map(({ salesChannelId }) => salesChannelId)).toEqual(
            defaultSalesChannels.map(({ id }) => id),
        );
    });
});
