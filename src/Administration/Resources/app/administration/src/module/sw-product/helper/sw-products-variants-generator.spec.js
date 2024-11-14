/**
 * @package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';

/** fixtures */
import currencies from './_mocks/testCurriencies.json';
import product from './_mocks/testProduct.json';

describe('/src/module/sw-product/helper/sw-products-variants-generator.spec.js', () => {
    let variantsGenerator;

    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve({ data: [] });
                    },
                },
                getBasicHeaders() {
                    return {};
                },
                sync() {
                    return true;
                },
            };
        });
        Shopware.Service().register('cacheApiService', () => {
            return {
                indexProducts() {},
            };
        });
        variantsGenerator = new VariantsGenerator();
    });

    it('should not filter variants with positive or negative prices', async () => {
        const expectedCreateQueue = [
            {
                options: [
                    {
                        id: 'f8081c78bb7b4c72bdd8dda79520f315',
                    },
                ],
                parentId: '0cf18788d25546a3a8dc856329aff57b',
                price: [
                    {
                        currencyId: 'e7d006b51e2d4f9c80de6be68206aba7',
                        gross: 762.586,
                        linked: true,
                        net: 762.586,
                    },
                    {
                        currencyId: '4f0b5be0f0a842218e3a899c66c19691',
                        gross: 18534.306099999998,
                        linked: true,
                        net: 18534.306099999998,
                    },
                    {
                        currencyId: '492a0955a83241c8b859b1c6c371c269',
                        gross: 5178.6521999999995,
                        linked: true,
                        net: 5178.6521999999995,
                    },
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        gross: 703.26,
                        linked: true,
                        net: 703.26,
                    },
                    {
                        currencyId: '42eb2cf984014b8db46309d593e59e7f',
                        gross: 618.0898182,
                        linked: true,
                        net: 618.0898182,
                    },
                    {
                        currencyId: 'c5ec1aad7cb54973bde4207dbe739aa7',
                        gross: 68.63274,
                        linked: true,
                        net: 68.63274,
                    },
                    {
                        currencyId: 'be219239630f4732b9f14d9d54b355cd',
                        gross: 3001.8158,
                        linked: true,
                        net: 3001.8158,
                    },
                    {
                        currencyId: '97623a923ef24f5bafa0aa3343209b38',
                        gross: 7286.1626,
                        linked: true,
                        net: 7286.1626,
                    },
                    {
                        currencyId: '058a99a092604b61a29a41064bb7500d',
                        gross: 811.7034709999999,
                        linked: true,
                        net: 811.7034709999999,
                    },
                ],
                productNumber: 'fdb84d0397414e03b2ed6f6821e3d945.1',
                stock: 0,
            },
            {
                options: [
                    {
                        id: '9eb83cc0627d43f2bba77b119ed847e9',
                    },
                ],
                parentId: '0cf18788d25546a3a8dc856329aff57b',
                price: [
                    {
                        currencyId: 'e7d006b51e2d4f9c80de6be68206aba7',
                        gross: 762.586,
                        linked: true,
                        net: 762.586,
                    },
                    {
                        currencyId: '4f0b5be0f0a842218e3a899c66c19691',
                        gross: 18534.306099999998,
                        linked: true,
                        net: 18534.306099999998,
                    },
                    {
                        currencyId: '492a0955a83241c8b859b1c6c371c269',
                        gross: 5178.6521999999995,
                        linked: true,
                        net: 5178.6521999999995,
                    },
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        gross: 683.26,
                        linked: true,
                        net: 683.26,
                    },
                    {
                        currencyId: '42eb2cf984014b8db46309d593e59e7f',
                        gross: 618.0898182,
                        linked: true,
                        net: 618.0898182,
                    },
                    {
                        currencyId: 'c5ec1aad7cb54973bde4207dbe739aa7',
                        gross: 68.63274,
                        linked: true,
                        net: 68.63274,
                    },
                    {
                        currencyId: 'be219239630f4732b9f14d9d54b355cd',
                        gross: 3001.8158,
                        linked: true,
                        net: 3001.8158,
                    },
                    {
                        currencyId: '97623a923ef24f5bafa0aa3343209b38',
                        gross: 7286.1626,
                        linked: true,
                        net: 7286.1626,
                    },
                    {
                        currencyId: '058a99a092604b61a29a41064bb7500d',
                        gross: 811.7034709999999,
                        linked: true,
                        net: 811.7034709999999,
                    },
                ],
                productNumber: 'fdb84d0397414e03b2ed6f6821e3d945.2',
                stock: 0,
            },
        ];

        function getCreateQueue() {
            return new Promise((resolve) => {
                const queueEventHandler = (data) => {
                    resolve(data.createQueue);
                };

                variantsGenerator.on('queues', queueEventHandler);

                variantsGenerator.generateVariants(currencies, product);
            });
        }

        expect(await getCreateQueue()).toEqual(expectedCreateQueue);
    });

    it('should emit `queues` event when calling generateVariants', async () => {
        function getQueueEventHandler() {
            return new Promise((resolve) => {
                const queueEventHandler = (data) => {
                    resolve(data);
                };

                variantsGenerator.on('queues', queueEventHandler);

                variantsGenerator.generateVariants(currencies, product);
            });
        }

        const data = await getQueueEventHandler();
        expect(data.deleteQueue).toHaveLength(0);
        expect(data.createQueue).toHaveLength(2);
    });

    it('should filter variants correctly', async () => {
        const newVariations = [
            [
                'e10fed21a07149958427cb5339ee4c31',
            ],
        ];

        const variationOnServer = {
            '455ff20cec764a2aab42d2282d08456c': {
                options: ['d6e90b99fe4842d487b53b59e50491a4'],
                productNumber: 'SW10000.1',
                productStates: '["is-physical"]',
            },
            a6ebe32c706b4a16a69041b31df5d7fb: {
                options: ['e10fed21a07149958427cb5339ee4c31'],
                productNumber: 'SW10000.2',
                productStates: '["is-download"]',
            },
        };

        variantsGenerator.product = product;
        const variants = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

        expect(variants).toEqual({
            createQueue: [],
            deleteQueue: [
                '455ff20cec764a2aab42d2282d08456c',
            ],
        });
    });
});
