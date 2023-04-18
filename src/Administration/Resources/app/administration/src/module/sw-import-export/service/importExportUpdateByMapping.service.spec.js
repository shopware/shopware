/**
 * @package system-settings
 */
import ImportExportUpdateByMappingService from 'src/module/sw-import-export/service/importExportUpdateByMapping.service';
// eslint-disable-next-line import/no-unresolved
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

describe('module/sw-import-export/service/importExportProfileMapping.service.spec.js', () => {
    let importExportUpdateByMappingService;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });

        importExportUpdateByMappingService = new ImportExportUpdateByMappingService(Shopware.EntityDefinition);
    });

    it('should return entity, path, relation by source entity and path', async () => {
        const { entity, path, relation, name } = importExportUpdateByMappingService.getEntity('product', 'manufacturer.translations.name');

        expect(entity).toBe('product_manufacturer');
        expect(path).toBe('manufacturer');
        expect(relation).toBe('many_to_one');
        expect(name).toBe('manufacturer');
    });

    it('should return selected mapped key by entity from update by mapping', async () => {
        const updateByMapping = [{
            entityName: 'product_manufacturer',
            mappedKey: 'translations.name',
        }];

        expect(importExportUpdateByMappingService.getSelected('product_manufacturer', updateByMapping)).toBe('translations.name');
        expect(importExportUpdateByMappingService.getSelected('property_group_option', updateByMapping)).toBe('id');
    });

    it('should remove unused entity from update by mapping', async () => {
        const profile = {
            sourceEntity: 'product',
            mapping: [
                {
                    mappedKey: 'manufacturer_name',
                    key: 'manufacturer.translations.name',
                },
            ],
            updateBy: [
                {
                    entityName: 'product_manufacturer',
                    mappedKey: 'translations.name',
                },
                {
                    entityName: 'property_group_option',
                    mappedKey: 'id',
                },
            ],
        };

        importExportUpdateByMappingService.removeUnusedMappings(profile);

        expect(profile.updateBy).toEqual([
            {
                entityName: 'product_manufacturer',
                mappedKey: 'translations.name',
            },
        ]);
    });

    it('should update mapped key of update by mapping', async () => {
        const profile = {
            sourceEntity: 'product',
            updateBy: [
                {
                    entityName: 'product_manufacturer',
                    mappedKey: 'translations.name',
                },
            ],
        };

        importExportUpdateByMappingService.updateMapping(profile, 'id', 'product_manufacturer');

        expect(profile.updateBy).toEqual([
            {
                entityName: 'product_manufacturer',
                mappedKey: 'id',
            },
        ]);
    });
});
