/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';
import EntityFactory from 'src/core/data/entity-factory.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import { deprecations } from 'test/_helper_/deprecations';

/** fixtures */
import currencies from './_mocks/testCurriencies.json';
import product from './_mocks/testProduct.json';

const entityFactory = new EntityFactory();

describe('/src/module/sw-product/helper/sw-products-variants-generator.spec.js', () => {
    let variantsGenerator;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });

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
        variantsGenerator = new VariantsGenerator();
    });

    function createProductEntity(originVariantRestrictions, variantRestrictions = originVariantRestrictions) {
        const productEntity = entityFactory.create('product', 'product-123');

        productEntity.getOrigin().variantRestrictions = originVariantRestrictions;
        productEntity.getDraft().variantRestrictions = originVariantRestrictions;
        productEntity.variantRestrictions = variantRestrictions;

        return productEntity;
    }

    function createProductEntityWithListingConfig(originConfig, variantListingConfig = originConfig) {
        const productEntity = entityFactory.create('product', 'product-123');

        productEntity.getOrigin().variantListingConfig = originConfig;
        productEntity.getDraft().variantListingConfig = originConfig;
        productEntity.variantListingConfig = variantListingConfig;

        return productEntity;
    }

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

                // eslint-disable-next-line listeners/no-missing-remove-event-listener
                variantsGenerator.on('queues', queueEventHandler);

                variantsGenerator.generateVariants(currencies, product);
            });
        }

        const data = await getQueueEventHandler();
        expect(data.deleteQueue).toHaveLength(0);
        expect(data.createQueue).toHaveLength(2);
    });

    it('should filter variants correctly', async () => {
        const newVariations = [['e10fed21a07149958427cb5339ee4c31']];

        const variationOnServer = {
            '455ff20cec764a2aab42d2282d08456c': {
                options: ['d6e90b99fe4842d487b53b59e50491a4'],
                productNumber: 'SW10000.1',
                productStates: '["is-physical"]',
                productType: '["physical"]',
            },
            a6ebe32c706b4a16a69041b31df5d7fb: {
                options: ['e10fed21a07149958427cb5339ee4c31'],
                productNumber: 'SW10000.2',
                productStates: '["is-download"]',
                productType: '["digital"]',
            },
        };

        variantsGenerator.product = product;
        const variants = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

        expect(variants).toEqual({
            createQueue: [],
            deleteQueue: ['455ff20cec764a2aab42d2282d08456c'],
        });
    });

    it('should delete existing variants matching newly added restrictions', async () => {
        const newVariations = [['d6e90b99fe4842d487b53b59e50491a4'], ['e10fed21a07149958427cb5339ee4c31']];

        const variationOnServer = {
            '455ff20cec764a2aab42d2282d08456c': {
                options: ['d6e90b99fe4842d487b53b59e50491a4'],
                productNumber: 'SW10000.1',
                productStates: '["is-physical"]',
                productType: '["physical"]',
            },
            a6ebe32c706b4a16a69041b31df5d7fb: {
                options: ['e10fed21a07149958427cb5339ee4c31'],
                productNumber: 'SW10000.2',
                productStates: '["is-download"]',
                productType: '["digital"]',
            },
        };

        variantsGenerator.product = {
            ...product,
            variantRestrictions: [
                {
                    id: 'restriction1',
                    values: [
                        {
                            id: 'value1',
                            group: 'group1',
                            options: ['e10fed21a07149958427cb5339ee4c31'],
                        },
                    ],
                },
            ],
        };

        const variants = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

        expect(variants).toEqual({
            createQueue: [],
            deleteQueue: ['a6ebe32c706b4a16a69041b31df5d7fb'],
        });
    });

    describe('filterRestrictions', () => {
        const mockCreateQueue = [
            {
                parentId: 'parent1',
                options: [{ id: 'option1' }, { id: 'option2' }],
            },
            {
                parentId: 'parent1',
                options: [{ id: 'option1' }, { id: 'option3' }],
            },
            {
                parentId: 'parent1',
                options: [{ id: 'option2' }, { id: 'option3' }],
            },
        ];

        it('should return createQueue when variantRestrictions is not an array', () => {
            variantsGenerator.product = { variantRestrictions: null };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.product = { variantRestrictions: undefined };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.product = { variantRestrictions: {} };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.product = { variantRestrictions: 'invalid' };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should return createQueue when variantRestrictions is empty array', () => {
            variantsGenerator.product = { variantRestrictions: [] };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should return createQueue when all restrictions have empty options', () => {
            variantsGenerator.product = {
                variantRestrictions: [
                    {
                        id: 'restriction1',
                        values: [{ id: 'value1', group: 'group1', options: [] }],
                    },
                    {
                        id: 'restriction2',
                        values: [],
                    },
                ],
            };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should filter out malformed restrictions and process valid ones', () => {
            variantsGenerator.product = {
                variantRestrictions: [
                    // Invalid: empty options
                    {
                        id: 'invalid1',
                        values: [{ id: 'value1', group: 'group1', options: [] }],
                    },
                    // Invalid: no values
                    {
                        id: 'invalid2',
                        values: [],
                    },
                    null,
                    {
                        id: 'valid1',
                        values: [
                            { id: 'value1', group: 'group1', options: ['option1'] },
                            { id: 'value2', group: 'group2', options: ['option2'] },
                        ],
                    },
                ],
            };

            const result = variantsGenerator.filterRestrictions(mockCreateQueue);

            expect(result).toEqual([
                {
                    parentId: 'parent1',
                    options: [{ id: 'option1' }, { id: 'option3' }],
                },
                {
                    parentId: 'parent1',
                    options: [{ id: 'option2' }, { id: 'option3' }],
                },
            ]);
        });

        it('should filter variants matching valid restrictions', () => {
            variantsGenerator.product = {
                variantRestrictions: [
                    {
                        id: 'restriction1',
                        values: [
                            { id: 'value1', group: 'group1', options: ['option1'] },
                            { id: 'value2', group: 'group2', options: ['option3'] },
                        ],
                    },
                ],
            };

            const result = variantsGenerator.filterRestrictions(mockCreateQueue);

            expect(result).toEqual([
                {
                    parentId: 'parent1',
                    options: [{ id: 'option1' }, { id: 'option2' }],
                },
                {
                    parentId: 'parent1',
                    options: [{ id: 'option2' }, { id: 'option3' }],
                },
            ]);
        });
    });

    describe('saveVariantRestrictions', () => {
        it('should resolve immediately when product is missing', async () => {
            variantsGenerator.product = null;
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantRestrictions([]);

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should resolve immediately when variant restrictions are undefined', async () => {
            variantsGenerator.product = {
                id: 'product-123',
            };
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantRestrictions();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should upsert product variant restrictions', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.product = createProductEntity(null, variantRestrictions);

            await variantsGenerator.saveVariantRestrictions();

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'product',
                        action: 'upsert',
                        payload: [
                            {
                                id: 'product-123',
                                variantRestrictions,
                            },
                        ],
                    },
                ],
                {},
                { 'indexing-behavior': 'disable-indexing' },
            );

            const calledRestrictions = syncSpy.mock.calls[0][0][0].payload[0].variantRestrictions;
            expect(calledRestrictions).not.toBe(variantRestrictions);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when product variant restrictions did not change', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.product = createProductEntity(variantRestrictions);

            const result = await variantsGenerator.saveVariantRestrictions();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should persist empty variant restrictions to clear previous exclusions', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.product = createProductEntity(variantRestrictions, []);

            await variantsGenerator.saveVariantRestrictions();

            expect(syncSpy.mock.calls[0][0][0].payload).toEqual([
                {
                    id: 'product-123',
                    variantRestrictions: [],
                },
            ]);

            syncSpy.mockRestore();
        });
    });

    describe('saveVariantListingConfig', () => {
        it('should apply the main product default for completely new variant products', async () => {
            const newProduct = JSON.parse(JSON.stringify(product));
            newProduct.variantListingConfig = null;

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, newProduct);
            });

            expect(newProduct.variantListingConfig).toEqual({ displayParent: true });
        });

        it('should apply the main product default when nothing was saved for a product created in the administration', async () => {
            const newProduct = JSON.parse(JSON.stringify(product));
            newProduct.variantListingConfig = { displayParent: null, mainVariantId: null, configuratorGroupConfig: null };

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, newProduct);
            });

            expect(newProduct.variantListingConfig).toEqual({ displayParent: true });
        });

        it.each([
            ['a variant', { displayParent: false, mainVariantId: null, configuratorGroupConfig: null }],
            ['a main variant', { displayParent: null, mainVariantId: 'variant-id', configuratorGroupConfig: null }],
            ['expanded variants', { displayParent: null, mainVariantId: null, configuratorGroupConfig: [] }],
        ])('should keep the saved presentation of %s when generating variants', async (_, variantListingConfig) => {
            const existingProduct = JSON.parse(JSON.stringify(product));
            existingProduct.variantListingConfig = { ...variantListingConfig };

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, existingProduct);
            });

            expect(existingProduct.variantListingConfig).toEqual(variantListingConfig);
        });

        it('should resolve immediately when product is missing', async () => {
            variantsGenerator.product = null;
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should resolve immediately when the variant listing config is not set', async () => {
            variantsGenerator.product = createProductEntityWithListingConfig(null);
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should upsert the variant listing config default applied during the generation', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantListingConfig = { displayParent: true };

            variantsGenerator.product = createProductEntityWithListingConfig(null, variantListingConfig);

            await variantsGenerator.saveVariantListingConfig();

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'product',
                        action: 'upsert',
                        payload: [
                            {
                                id: 'product-123',
                                variantListingConfig,
                            },
                        ],
                    },
                ],
                {},
                { 'indexing-behavior': 'use-queue-indexing' },
            );

            const calledConfig = syncSpy.mock.calls[0][0][0].payload[0].variantListingConfig;
            expect(calledConfig).not.toBe(variantListingConfig);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when the variant listing config did not change', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = createProductEntityWithListingConfig({
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [
                    {
                        id: 'group-1',
                        representation: 'box',
                        expressionForListings: true,
                    },
                ],
            });

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });
    });

    describe('indexProducts', () => {
        // @deprecated tag:v6.8.0 - The test will be removed with indexProducts()
        it.deprecated('v6.8.0.0')('should report the deprecation and still request the indexing', () => {
            const generator = new VariantsGenerator();
            generator.cacheService = { indexProducts: jest.fn() };

            generator.indexProducts(['product-id']);

            expect(deprecations).toHaveTriggered('VariantsGenerator.indexProducts() is deprecated');
            expect(generator.cacheService.indexProducts).toHaveBeenCalledWith(['product-id']);
        });
    });
});
