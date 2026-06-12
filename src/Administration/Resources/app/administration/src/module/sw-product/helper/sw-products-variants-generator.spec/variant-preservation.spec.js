/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';

/** fixtures */
import currencies from '../_mocks/testCurriencies.json';
import product from '../_mocks/testProduct.json';

describe('sw-products-variants-generator.spec/variant-preservation.spec.js', () => {
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
});
