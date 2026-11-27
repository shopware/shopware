/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';
import EntityFactory from 'src/core/data/entity-factory.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

const entityFactory = new EntityFactory();

describe('src/module/sw-product/helper/sw-products-variants-generator saveConfiguratorSettings', () => {
    let variantsGenerator;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });

        Shopware.Service().register('syncService', () => ({
            httpClient: {},
            sync() {
                return Promise.resolve();
            },
        }));
        Shopware.Service().register('cacheApiService', () => ({ indexProducts() {} }));
        variantsGenerator = new VariantsGenerator();
    });
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
            { 'single-operation': 1, 'indexing-behavior': 'use-queue-indexing' },
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

    it('should send the id of new settings, so saving them again updates the same rows', async () => {
        const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
        variantsGenerator.product = { id: 'product-123' };

        const setting = entityFactory.create('product_configurator_setting', 'new-setting-id');
        setting.optionId = 'option-1';

        await variantsGenerator.saveConfiguratorSettings([setting], [{ options: [{ id: 'option-1' }] }]);

        expect(syncSpy.mock.calls[0][0][0].payload[0]).toEqual(
            expect.objectContaining({ id: 'new-setting-id', optionId: 'option-1', productId: 'product-123' }),
        );

        syncSpy.mockRestore();
    });

    it('should send the fields of the settings without their nested option', async () => {
        const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
        variantsGenerator.product = { id: 'product-123' };

        const setting = entityFactory.create('product_configurator_setting', 'setting-id');
        setting.optionId = 'option-1';
        setting.option = { id: 'option-1', groupId: 'group-1', name: 'Red' };
        setting.mediaId = 'media-1';
        setting.price = [{ currencyId: 'currency-1', gross: 10, net: 8.4, linked: true }];
        setting.position = 2;
        setting.customFields = { foo: 'bar' };

        await variantsGenerator.saveConfiguratorSettings([setting], [{ options: [{ id: 'option-1' }] }]);

        expect(syncSpy.mock.calls[0][0][0].payload).toEqual([
            {
                id: 'setting-id',
                productId: 'product-123',
                optionId: 'option-1',
                mediaId: 'media-1',
                price: [{ currencyId: 'currency-1', gross: 10, net: 8.4, linked: true }],
                position: 2,
                customFields: { foo: 'bar' },
            },
        ]);

        syncSpy.mockRestore();
    });

    it('should send position 0 for a new setting without a position', async () => {
        const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
        variantsGenerator.product = { id: 'product-123' };

        const setting = entityFactory.create('product_configurator_setting', 'setting-id');
        setting.optionId = 'option-1';

        await variantsGenerator.saveConfiguratorSettings([setting], [{ options: [{ id: 'option-1' }] }]);

        expect(syncSpy.mock.calls[0][0][0].payload[0].position).toBe(0);

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
                options: [{ id: 'new-option-1' }, { id: 'new-option-2' }],
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
        expect(calledPayload.map((s) => s.optionId).sort()).toEqual(['option-existing', 'option-new-in-queue']);

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
