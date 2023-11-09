/**
 * @package services-settings
 */
import ImportExportProfileMappingService from 'src/module/sw-import-export/service/importExportProfileMapping.service';
// eslint-disable-next-line import/no-unresolved
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
        expect(typeof importExportProfileMappingService.validate).toBe('function');
    });

    it('product: should not find any missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('product', mappings.productProfileOnlyRequired);

        expect(invalidFields.missingRequiredFields).toHaveLength(0);
    });

    [
        'id',
        'versionId',
        'parentVersionId',
        'stock',
        'productManufacturerVersionId',
        'taxId',
        'productNumber',
    ].forEach(fieldName => {
        it(`product: should find missing required field ${fieldName}`, async () => {
            const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== fieldName);
            const invalidFields = importExportProfileMappingService.validate('product', mapping);

            expect(invalidFields.missingRequiredFields).toHaveLength(1);
            expect(invalidFields.missingRequiredFields).toContain(fieldName);
        });
    });

    it('product: should find missing required field name', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.name');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields).toHaveLength(1);
        expect(invalidFields.missingRequiredFields).toContain('name');
    });

    it('product: should find missing required field createdAt', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.createdAt');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields).toHaveLength(1);
        expect(invalidFields.missingRequiredFields).toContain('createdAt');
    });

    it('product: should return all missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('product', []);

        expect(invalidFields.missingRequiredFields.sort()).toEqual([
            'id',
            'versionId',
            'parentVersionId',
            'productManufacturerVersionId',
            'productMediaVersionId',
            'taxId',
            'productNumber',
            'stock',
            'name',
            'cmsPageVersionId',
            'createdAt',
        ].sort());
    });

    it('product: should find missing required when parentProduct is existing', async () => {
        const mapping = mappings.productDuplicateProfileOnlyRequired.filter(field => field.key === 'productNumber');
        const invalidFields = importExportProfileMappingService.validate(
            'product',
            mapping,
            mappings.productDuplicateProfileOnlyRequired,
        );

        expect(invalidFields.missingRequiredFields).toEqual(['id', 'taxId']);
    });

    it('product: should not find any missing required when parentProduct is existing', async () => {
        const invalidFields = importExportProfileMappingService.validate(
            'product',
            mappings.productDuplicateProfileOnlyRequired,
            mappings.productDuplicateProfileOnlyRequired,
        );

        expect(invalidFields.missingRequiredFields).toHaveLength(0);
    });

    it('product: should find missing required when key.id is existing', async () => {
        const invalidFields = importExportProfileMappingService.validate(
            'product',
            [
                {
                    id: 'fc416f509b0b46fabb8cd8728cf63531',
                    key: 'tax.id',
                    mappedKey: 'tax_id',
                },
            ],
            mappings.productDuplicateProfileOnlyRequired,
        );

        expect(invalidFields.missingRequiredFields).toEqual(['id', 'productNumber']);
    });

    it('media: should not find any missing required fields', async () => {
        const invalidFields = importExportProfileMappingService.validate('media', mappings.mediaProfileOnlyRequired);
        expect(invalidFields.missingRequiredFields).toHaveLength(0);
    });

    it('media: should find missing required field id', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'id');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);
        expect(invalidFields.missingRequiredFields).toHaveLength(1);
        expect(invalidFields.missingRequiredFields).toContain('id');
    });

    it('media: should find missing required field createdAt', async () => {
        const mapping = mappings.productProfileOnlyRequired.filter(field => field.key !== 'translations.DEFAULT.createdAt');
        const invalidFields = importExportProfileMappingService.validate('product', mapping);

        expect(invalidFields.missingRequiredFields).toHaveLength(1);
        expect(invalidFields.missingRequiredFields).toContain('createdAt');
    });

    it('category: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('category', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'displayNestedProducts',
            'type',
            'productAssignmentType',
            'translations.DEFAULT.name',
        ]);
    });

    it('category: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('category', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'displayNestedProducts',
            'type',
            'productAssignmentType',
            'translations.DEFAULT.name',
        ]);
    });

    it('product_cross_selling: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product_cross_selling', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'position',
            'type',
            'product.id',
            'translations.DEFAULT.name',
        ]);
    });

    it('product_cross_selling: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product_cross_selling', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'position',
            'type',
            'product.id',
            'product.price.DEFAULT.net',
            'product.price.DEFAULT.gross',
            'product.productNumber',
            'product.tax.id',
            'product.tax.taxRate',
            'product.tax.name',
            'product.tax.position',
            'product.translations.DEFAULT.name',
            'product.stock',
            'translations.DEFAULT.name',
        ]);
    });

    it('media: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('media', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
        ]);
    });

    it('media: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('media', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
        ]);
    });

    it('newsletter_recipient: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('newsletter_recipient', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'email',
            'status',
            'hash',
            'language.id',
            'salesChannel.id',
        ]);
    });

    it('newsletter_recipient: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('newsletter_recipient', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'email',
            'status',
            'hash',
            'language.id',
            'language.name',
            'language.locale.id',
            'language.locale.code',
            'language.locale.translations.DEFAULT.name',
            'language.locale.translations.DEFAULT.territory',
            'salesChannel.id',
            'salesChannel.accessKey',
            'salesChannel.translations.DEFAULT.name',
            'salesChannel.translations.DEFAULT.homeEnabled',
            'salesChannel.type.id',
            'salesChannel.type.translations.DEFAULT.name',
            'salesChannel.customerGroup.id',
            'salesChannel.customerGroup.translations.DEFAULT.name',
            'salesChannel.currency.id',
            'salesChannel.currency.factor',
            'salesChannel.currency.symbol',
            'salesChannel.currency.isoCode',
            'salesChannel.currency.translations.DEFAULT.shortName',
            'salesChannel.currency.translations.DEFAULT.name',
            'salesChannel.currency.itemRounding',
            'salesChannel.currency.totalRounding',
            'salesChannel.paymentMethod.id',
            'salesChannel.paymentMethod.translations.DEFAULT.name',
            'salesChannel.shippingMethod.id',
            'salesChannel.shippingMethod.taxType',
            'salesChannel.shippingMethod.deliveryTime.id',
            'salesChannel.shippingMethod.translations.DEFAULT.name',
            'salesChannel.shippingMethod.availabilityRule.id',
            'salesChannel.country.id',
            'salesChannel.country.translations.DEFAULT.name',
            'salesChannel.country.translations.DEFAULT.addressFormat',
            'salesChannel.navigationCategory.id',
            'salesChannel.navigationCategory.displayNestedProducts',
            'salesChannel.navigationCategory.type',
            'salesChannel.navigationCategory.productAssignmentType',
            'salesChannel.navigationCategory.translations.DEFAULT.name',
        ]);
    });

    it('product: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'tax.id',
            'translations.DEFAULT.name',
            'stock',
        ]);
    });

    it('product: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'tax.id',
            'tax.taxRate',
            'tax.name',
            'tax.position',
            'translations.DEFAULT.name',
            'stock',
        ]);
    });

    it('property_group_option: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('property_group_option', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'group.id',
            'translations.DEFAULT.name',
        ]);
    });

    it('property_group_option: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('property_group_option', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'group.id',
            'group.displayType',
            'group.sortingType',
            'group.translations.DEFAULT.name',
            'translations.DEFAULT.name',
        ]);
    });

    it('product_configurator_setting: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product_configurator_setting', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'product.id',
            'option.id',
        ]);
    });

    it('product_configurator_setting: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product_configurator_setting', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'product.id',
            'product.price.DEFAULT.net',
            'product.price.DEFAULT.gross',
            'product.productNumber',
            'product.tax.id',
            'product.tax.taxRate',
            'product.tax.name',
            'product.tax.position',
            'product.translations.DEFAULT.name',
            'product.stock',
            'option.id',
            'option.group.id',
            'option.group.displayType',
            'option.group.sortingType',
            'option.group.translations.DEFAULT.name',
            'option.translations.DEFAULT.name',
        ]);
    });
});
