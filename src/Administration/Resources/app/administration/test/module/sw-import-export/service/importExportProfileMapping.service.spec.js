import ImportExportProfileMappingService from 'src/module/sw-import-export/service/importExportProfileMapping.service';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import * as mappings from './mocks/mappings.mock';

describe('module/sw-import-export/service/importExportProfileMapping.service.spec.js', () => {
    let importExportProfileMappingService;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });

        importExportProfileMappingService = new ImportExportProfileMappingService(Shopware.EntityDefinition);
    });

    it('should contain all public functions', async () => {
        expect(typeof importExportProfileMappingService.validate).toEqual('function');
    });

    it('product: should not find any missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('product', mappings.productProfileOnlyRequired);

        expect(invalidFields.missingRequiredFields.length).toEqual(0);
    });

    [
        'id',
        'versionId',
        'parentVersionId',
        'stock',
        'productManufacturerVersionId',
        'taxId',
        'productNumber'
    ].forEach(fieldName => {
        it(`product: should find missing required field ${fieldName}`, async () => {
            const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== fieldName);
            const invalidFields = importExportProfileMappingService.validate('product', mapping);

            expect(invalidFields.missingRequiredFields.length).toEqual(1);
            expect(invalidFields.missingRequiredFields).toContain(fieldName);
        });
    });

    it('product: should find missing required field name', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.name');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields.length).toEqual(1);
        expect(invalidFields.missingRequiredFields).toContain('name');
    });

    it('product: should find missing required field createdAt', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.createdAt');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields.length).toEqual(1);
        expect(invalidFields.missingRequiredFields).toContain('createdAt');
    });

    it('product: should return all missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('product', []);

        expect(invalidFields.missingRequiredFields.sort()).toEqual([
            'id',
            'versionId',
            'parentVersionId',
            'productManufacturerVersionId',
            'taxId',
            'productNumber',
            'stock',
            'name',
            'cmsPageVersionId',
            'createdAt'
        ].sort());
    });

    it('product: should find missing required when parentProduct is existing', async () => {
        const mapping = mappings.productDuplicateProfileOnlyRequired.filter(field => field.key === 'productNumber');
        const invalidFields = importExportProfileMappingService.validate(
            'product',
            mapping,
            mappings.productDuplicateProfileOnlyRequired
        );

        expect(invalidFields.missingRequiredFields).toEqual(['id', 'taxId']);
    });

    it('product: should not find any missing required when parentProduct is existing', async () => {
        const invalidFields = importExportProfileMappingService.validate(
            'product',
            mappings.productDuplicateProfileOnlyRequired,
            mappings.productDuplicateProfileOnlyRequired
        );

        expect(invalidFields.missingRequiredFields.length).toEqual(0);
    });

    it('product: should find missing required when key.id is existing', async () => {
        const invalidFields = importExportProfileMappingService.validate('product',
            [
                {
                    id: 'fc416f509b0b46fabb8cd8728cf63531',
                    key: 'tax.id',
                    mappedKey: 'tax_id'
                }
            ],
            mappings.productDuplicateProfileOnlyRequired);

        expect(invalidFields.missingRequiredFields).toEqual(['id', 'productNumber']);
    });

    it('media: should not find any missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('media', mappings.mediaProfileOnlyRequired);

        expect(invalidFields.missingRequiredFields.length).toEqual(0);
    });

    it('media: should find missing required field id', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'id');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields.length).toEqual(1);
        expect(invalidFields.missingRequiredFields).toContain('id');
    });

    it('media: should find missing required field createdAt', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.createdAt');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields.length).toEqual(1);
        expect(invalidFields.missingRequiredFields).toContain('createdAt');
    });
});
