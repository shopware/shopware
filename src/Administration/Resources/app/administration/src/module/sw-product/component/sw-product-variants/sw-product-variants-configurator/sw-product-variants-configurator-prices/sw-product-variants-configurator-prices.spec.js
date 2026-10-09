/*
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const currencySearch = jest.fn(() =>
    Promise.resolve([
        {
            id: 'currencyId',
            name: 'Euro',
            symbol: '€',
        },
    ]),
);

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-variants-configurator-prices', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'currency') {
                                return { search: currencySearch };
                            }

                            return {};
                        },
                    },
                },
                stubs: {
                    'sw-simple-search-field': true,
                    'sw-data-grid': true,
                    'sw-loader': true,
                },
            },
            props: {
                product: {
                    taxId: 'taxId',
                    configuratorSettings: [],
                },
                selectedGroups: [],
            },
        },
    );
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices', () => {
    beforeEach(() => {
        currencySearch.mockClear();
    });

    it('should load all currencies for the surcharge columns', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(currencySearch).toHaveBeenCalledTimes(1);
        expect(currencySearch.mock.calls[0][0].getLimit()).toBe(500);
        expect(wrapper.vm.currenciesList).toHaveLength(1);
    });
});
