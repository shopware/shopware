/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

const currencySearch = jest.fn(() =>
    Promise.resolve([
        {
            id: 'currencyId',
            isSystemDefault: true,
            symbol: '€',
        },
    ]),
);

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-promotion-v2-settings-discount-type', {
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

                            return { create: () => ({}) };
                        },
                    },
                    acl: {
                        can: () => true,
                    },
                },
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-modal': true,
                    'sw-one-to-many-grid': true,
                },
            },
            props: {
                discount: {
                    id: 'discountId',
                    isNew: () => false,
                    type: 'absolute',
                    value: 10,
                    maxValue: null,
                    scope: 'cart',
                    applierKey: 'ALL',
                },
                discountScope: 'basic',
            },
        },
    );
}

describe('src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-discount-type', () => {
    beforeEach(() => {
        currencySearch.mockClear();
    });

    it('should load all currencies for the advanced prices', async () => {
        await createWrapper();
        await flushPromises();

        expect(currencySearch).toHaveBeenCalledTimes(1);
        expect(currencySearch.mock.calls[0][0].getLimit()).toBe(500);
    });
});
