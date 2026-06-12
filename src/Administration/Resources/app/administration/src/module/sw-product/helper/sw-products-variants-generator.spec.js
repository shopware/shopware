/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
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
            deleteQueue: [
                '455ff20cec764a2aab42d2282d08456c',
            ],
        });
    });

    describe('filterRestrictions', () => {
        const mockCreateQueue = [
            {
                parentId: 'parent1',
                options: [
                    { id: 'option1' },
                    { id: 'option2' },
                ],
            },
            {
                parentId: 'parent1',
                options: [
                    { id: 'option1' },
                    { id: 'option3' },
                ],
            },
            {
                parentId: 'parent1',
                options: [
                    { id: 'option2' },
                    { id: 'option3' },
                ],
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
                        values: [
                            { id: 'value1', group: 'group1', options: [] },
                        ],
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
                    options: [
                        { id: 'option1' },
                        { id: 'option3' },
                    ],
                },
                {
                    parentId: 'parent1',
                    options: [
                        { id: 'option2' },
                        { id: 'option3' },
                    ],
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
                    options: [
                        { id: 'option1' },
                        { id: 'option2' },
                    ],
                },
                {
                    parentId: 'parent1',
                    options: [
                        { id: 'option2' },
                        { id: 'option3' },
                    ],
                },
            ]);
        });
    });

    describe('saveConfiguratorSettings', () => {
        it('should resolve immediately when configuratorSettings is null', async () => {
            const result = await variantsGenerator.saveConfiguratorSettings(null);
            expect(result).toBeUndefined();
        });

        it('should resolve immediately when configuratorSettings is empty', async () => {
            const result = await variantsGenerator.saveConfiguratorSettings([]);
            expect(result).toBeUndefined();
        });

        it('should call syncService.sync with upsert action and cloned settings', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    mediaId: 'media-1',
                    position: 1,
                    price: { gross: 10, net: 8.4 },
                    customFields: { foo: 'bar' },
                    isNew: () => false,
                },
                {
                    id: 'setting-2',
                    optionId: 'option-2',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings);

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'product_configurator_setting',
                        action: 'upsert',
                        payload: expect.arrayContaining([
                            expect.objectContaining({
                                id: 'setting-1',
                                productId: 'product-123',
                                optionId: 'option-1',
                            }),
                            expect.objectContaining({
                                id: 'setting-2',
                                productId: 'product-123',
                                optionId: 'option-2',
                            }),
                        ]),
                    },
                ],
                {},
                { 'single-operation': 1 },
            );

            syncSpy.mockRestore();
        });

        it('should always set productId to current product', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'current-product-id',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    productId: 'old-product-id', // Should be overwritten
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload[0];
            expect(calledPayload.productId).toBe('current-product-id');

            syncSpy.mockRestore();
        });

        it('should clone settings to avoid mutating original data', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const originalSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    productId: 'original-product-id',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(originalSettings);

            expect(originalSettings[0].productId).toBe('original-product-id');

            syncSpy.mockRestore();
        });

        it('should always include existing settings (isNew returns false) regardless of createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    isNew: () => false,
                },
                {
                    id: 'setting-2',
                    optionId: 'option-2',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, []);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(2);
            expect(calledPayload[0].optionId).toBe('option-1');
            expect(calledPayload[1].optionId).toBe('option-2');

            syncSpy.mockRestore();
        });

        it('should filter out falsely new settings not in createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'existing-option',
                    isNew: () => true,
                },
                {
                    id: 'setting-2',
                    optionId: 'real-existing-option',
                    isNew: () => false,
                },
            ];

            const createQueue = [];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(1);
            expect(calledPayload[0].optionId).toBe('real-existing-option');

            syncSpy.mockRestore();
        });

        it('should include truly new settings that are in createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'new-option-1',
                    isNew: () => true,
                },
                {
                    id: 'setting-2',
                    optionId: 'new-option-2',
                    isNew: () => true,
                },
                {
                    id: 'setting-3',
                    optionId: 'existing-option',
                    isNew: () => false,
                },
            ];

            const createQueue = [
                {
                    options: [
                        { id: 'new-option-1' },
                        { id: 'new-option-2' },
                    ],
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(3);

            syncSpy.mockRestore();
        });

        it('should include existing and new-in-queue settings and filter out new-not-in-queue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-existing',
                    optionId: 'option-existing',
                    isNew: () => false,
                },
                {
                    id: 'setting-new-in-queue',
                    optionId: 'option-new-in-queue',
                    isNew: () => true,
                },
                {
                    id: 'setting-new-not-in-queue',
                    optionId: 'option-new-not-in-queue',
                    isNew: () => true,
                },
            ];

            const createQueue = [
                {
                    options: [{ id: 'option-new-in-queue' }],
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(2);
            expect(calledPayload.map((s) => s.optionId).sort()).toEqual([
                'option-existing',
                'option-new-in-queue',
            ]);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when all settings are filtered out', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.product = {
                id: 'product-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'existing-option',
                    isNew: () => true,
                },
            ];

            const createQueue = [];

            const result = await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            expect(syncSpy).not.toHaveBeenCalled();
            expect(result).toBeUndefined();

            syncSpy.mockRestore();
        });
    });

    describe('preservation of existing variants when a new axis is added', () => {
        const RED = 'option-red';
        const BLUE = 'option-blue';
        const SIZE_S = 'option-size-s';
        const SIZE_L = 'option-size-l';

        function configuratorSettings(optionIds) {
            return optionIds.map((id) => ({
                option: { id },
                price: null,
            }));
        }

        it('should preserve existing single-axis variants when a new axis is introduced', async () => {
            variantsGenerator.product = {
                ...product,
                configuratorSettings: configuratorSettings([
                    RED,
                    BLUE,
                    SIZE_S,
                    SIZE_L,
                ]),
            };

            const newVariations = [
                [
                    RED,
                    SIZE_S,
                ],
                [
                    RED,
                    SIZE_L,
                ],
                [
                    BLUE,
                    SIZE_S,
                ],
                [
                    BLUE,
                    SIZE_L,
                ],
            ];

            const variationOnServer = {
                'variant-id-red': {
                    options: [RED],
                    productNumber: 'SW.RED',
                },
                'variant-id-blue': {
                    options: [BLUE],
                    productNumber: 'SW.BLUE',
                },
            };

            const result = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

            // Both existing variants must survive — no deletions.
            expect(result.deleteQueue).toEqual([]);

            // two permutations are adopted, the remaining two are created
            expect(result.createQueue).toHaveLength(2);

            const adoptedOptionSets = variantsGenerator.extendExistingVariantOptions.reduce(
                (acc, { productId, optionId }) => {
                    if (!acc[productId]) acc[productId] = [];
                    acc[productId].push(optionId);
                    return acc;
                },
                {},
            );

            // each preserved variant gets exactly one option from the new axis
            expect(Object.keys(adoptedOptionSets).sort()).toEqual([
                'variant-id-blue',
                'variant-id-red',
            ]);
            expect(adoptedOptionSets['variant-id-red']).toHaveLength(1);
            expect([
                SIZE_S,
                SIZE_L,
            ]).toContain(adoptedOptionSets['variant-id-red'][0]);
            expect(adoptedOptionSets['variant-id-blue']).toHaveLength(1);
            expect([
                SIZE_S,
                SIZE_L,
            ]).toContain(adoptedOptionSets['variant-id-blue'][0]);

            // each permutation is adopted by at most one variant
            const adoptedSizeOptions = [
                adoptedOptionSets['variant-id-red'][0],
                adoptedOptionSets['variant-id-blue'][0],
            ];

            // adopted permutations are not recreated
            const createdOptionIds = result.createQueue.map((variant) => variant.options.map((o) => o.id).sort());
            expect(createdOptionIds).toHaveLength(2);
            createdOptionIds.forEach((combo) => {
                // created combos pair a color with the size not used by adoption
                expect(combo).toHaveLength(2);
            });

            // all four permutations are covered: 2 preserved + 2 created
            const allResultingCombos = [
                ...createdOptionIds,
                [
                    RED,
                    adoptedSizeOptions[0],
                ].sort(),
                [
                    BLUE,
                    adoptedSizeOptions[1],
                ].sort(),
            ];
            const allSignatures = allResultingCombos.map((c) => c.join('|')).sort();
            expect(allSignatures).toEqual(
                [
                    [
                        BLUE,
                        SIZE_L,
                    ]
                        .sort()
                        .join('|'),
                    [
                        BLUE,
                        SIZE_S,
                    ]
                        .sort()
                        .join('|'),
                    [
                        RED,
                        SIZE_L,
                    ]
                        .sort()
                        .join('|'),
                    [
                        RED,
                        SIZE_S,
                    ]
                        .sort()
                        .join('|'),
                ].sort(),
            );
        });

        it('should still delete variants whose options were fully removed from the axis', async () => {
            variantsGenerator.product = {
                ...product,
                // "blue" was removed from the selection; "red" stays, "size"
                // is a new axis.
                configuratorSettings: configuratorSettings([
                    RED,
                    SIZE_S,
                    SIZE_L,
                ]),
            };

            const newVariations = [
                [
                    RED,
                    SIZE_S,
                ],
                [
                    RED,
                    SIZE_L,
                ],
            ];

            const variationOnServer = {
                'variant-id-red': {
                    options: [RED],
                    productNumber: 'SW.RED',
                },
                'variant-id-blue': {
                    options: [BLUE],
                    productNumber: 'SW.BLUE',
                },
            };

            const result = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

            // red is preserved; blue was removed by the user and must stay deleted
            expect(result.deleteQueue).toEqual(['variant-id-blue']);
            expect(result.createQueue).toHaveLength(1);

            expect(variantsGenerator.extendExistingVariantOptions).toHaveLength(1);
            expect(variantsGenerator.extendExistingVariantOptions[0].productId).toBe('variant-id-red');
            expect([
                SIZE_S,
                SIZE_L,
            ]).toContain(variantsGenerator.extendExistingVariantOptions[0].optionId);
        });

        it('should not queue M2M extensions when isAddOnly is enabled', async () => {
            variantsGenerator.product = {
                ...product,
                configuratorSettings: configuratorSettings([
                    RED,
                    SIZE_S,
                ]),
            };

            const newVariations = [
                [
                    RED,
                    SIZE_S,
                ],
            ];

            const variationOnServer = {
                'variant-id-red': {
                    options: [RED],
                    productNumber: 'SW.RED',
                },
            };

            const result = await variantsGenerator.filterVariations(
                newVariations,
                variationOnServer,
                currencies,
                true, // isAddOnly
            );

            // add-only mode keeps the legacy behavior: nothing to rescue
            expect(result.deleteQueue).toEqual([]);
            expect(variantsGenerator.extendExistingVariantOptions).toEqual([]);
        });

        it('saveExistingVariantOptionExtensions should sync product_option upserts', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.extendExistingVariantOptions = [
                { productId: 'variant-1', optionId: SIZE_S },
                { productId: 'variant-2', optionId: SIZE_L },
            ];
            variantsGenerator.productIds = [];

            await variantsGenerator.saveExistingVariantOptionExtensions();

            expect(syncSpy).toHaveBeenCalledTimes(1);
            expect(syncSpy.mock.calls[0][0]).toEqual([
                {
                    entity: 'product_option',
                    action: 'upsert',
                    payload: [
                        { productId: 'variant-1', optionId: SIZE_S },
                        { productId: 'variant-2', optionId: SIZE_L },
                    ],
                },
            ]);

            // preserved variants are queued for re-indexing
            expect(variantsGenerator.productIds.sort()).toEqual([
                'variant-1',
                'variant-2',
            ]);

            // Queue is drained after flush so a subsequent call is a no-op.
            expect(variantsGenerator.extendExistingVariantOptions).toEqual([]);

            syncSpy.mockRestore();
        });

        it('saveExistingVariantOptionExtensions should no-op on an empty queue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.extendExistingVariantOptions = [];

            const result = await variantsGenerator.saveExistingVariantOptionExtensions();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should preserve existing variants when two new axes are introduced simultaneously', async () => {
            const MAT_COTTON = 'option-material-cotton';
            const MAT_WOOL = 'option-material-wool';

            variantsGenerator.product = {
                ...product,
                configuratorSettings: configuratorSettings([
                    RED,
                    BLUE,
                    SIZE_S,
                    SIZE_L,
                    MAT_COTTON,
                    MAT_WOOL,
                ]),
            };

            // Color x Size x Material = 8 permutations; each existing variant
            // adopts one and picks up one option per new axis
            const newVariations = [
                [RED, SIZE_S, MAT_COTTON],
                [RED, SIZE_S, MAT_WOOL],
                [RED, SIZE_L, MAT_COTTON],
                [RED, SIZE_L, MAT_WOOL],
                [BLUE, SIZE_S, MAT_COTTON],
                [BLUE, SIZE_S, MAT_WOOL],
                [BLUE, SIZE_L, MAT_COTTON],
                [BLUE, SIZE_L, MAT_WOOL],
            ];

            const variationOnServer = {
                'variant-id-red': { options: [RED], productNumber: 'SW.RED' },
                'variant-id-blue': { options: [BLUE], productNumber: 'SW.BLUE' },
            };

            const result = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

            expect(result.deleteQueue).toEqual([]);
            // 2 adopted + 6 freshly created = 8 - 2.
            expect(result.createQueue).toHaveLength(6);

            const adopted = variantsGenerator.extendExistingVariantOptions.reduce((acc, { productId, optionId }) => {
                (acc[productId] ??= []).push(optionId);
                return acc;
            }, {});

            expect(Object.keys(adopted).sort()).toEqual(['variant-id-blue', 'variant-id-red']);

            // Each preserved variant must pick up exactly one option per newly introduced axis.
            for (const ids of Object.values(adopted)) {
                expect(ids).toHaveLength(2);
                expect(ids).toEqual(expect.arrayContaining([
                    expect.stringMatching(/^option-size-/),
                    expect.stringMatching(/^option-material-/),
                ]));
            }
        });
    });

    describe('adoption state lifecycle', () => {
        const RED = 'option-red';
        const BLUE = 'option-blue';
        const SIZE_S = 'option-size-s';
        const SIZE_L = 'option-size-l';

        it('should persist isNew configurator settings for options granted only via adoption', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            const settings = [
                {
                    id: 'setting-red',
                    optionId: RED,
                    option: { id: RED },
                    price: null,
                    isNew: () => false,
                },
                {
                    id: 'setting-blue',
                    optionId: BLUE,
                    option: { id: BLUE },
                    price: null,
                    isNew: () => false,
                },
                {
                    id: 'setting-size-s',
                    optionId: SIZE_S,
                    option: { id: SIZE_S },
                    price: null,
                    isNew: () => true,
                },
                {
                    id: 'setting-size-l',
                    optionId: SIZE_L,
                    option: { id: SIZE_L },
                    price: null,
                    isNew: () => true,
                },
            ];

            variantsGenerator.product = {
                ...product,
                configuratorSettings: settings,
            };

            const result = await variantsGenerator.filterVariations(
                [
                    [RED, SIZE_S],
                    [RED, SIZE_L],
                    [BLUE, SIZE_S],
                    [BLUE, SIZE_L],
                ],
                {
                    'variant-id-red': { options: [RED], productNumber: 'SW.RED' },
                    'variant-id-blue': { options: [BLUE], productNumber: 'SW.BLUE' },
                },
                currencies,
            );

            // both variants adopt the first size value, so SIZE_S is in no created variant
            const createdOptionIds = result.createQueue.flatMap((variant) => variant.options.map((o) => o.id));
            expect(createdOptionIds).not.toContain(SIZE_S);

            // its configurator setting must still be persisted
            await variantsGenerator.saveConfiguratorSettings(settings, result.createQueue);

            const payloadOptionIds = syncSpy.mock.calls[0][0][0].payload.map((setting) => setting.optionId);
            expect(payloadOptionIds).toEqual(
                expect.arrayContaining([
                    RED,
                    BLUE,
                    SIZE_S,
                    SIZE_L,
                ]),
            );

            syncSpy.mockRestore();
        });

        it('should reset queued adoption state when a generation pass starts with an empty selection', async () => {
            // leftovers from an earlier preview pass
            variantsGenerator.extendExistingVariantOptions = [
                { productId: 'variant-id-red', optionId: SIZE_S },
            ];
            variantsGenerator.adoptedOptionIds = new Set([SIZE_S]);

            const queuesPromise = new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
            });

            // empty selection takes the path that never reaches filterVariations
            variantsGenerator.generateVariants(currencies, {
                ...product,
                configuratorSettings: [],
            });

            const queues = await queuesPromise;
            expect(queues).toEqual({ createQueue: [], deleteQueue: [] });

            // stale adoption state must not survive into the next save
            expect(variantsGenerator.extendExistingVariantOptions).toEqual([]);
            expect(variantsGenerator.adoptedOptionIds.size).toBe(0);
        });
    });
});
