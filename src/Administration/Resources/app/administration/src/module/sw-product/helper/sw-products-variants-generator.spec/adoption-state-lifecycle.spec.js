/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';

/** fixtures */
import currencies from '../_mocks/testCurriencies.json';
import product from '../_mocks/testProduct.json';

describe('sw-products-variants-generator.spec/adoption-state-lifecycle.spec.js', () => {
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
