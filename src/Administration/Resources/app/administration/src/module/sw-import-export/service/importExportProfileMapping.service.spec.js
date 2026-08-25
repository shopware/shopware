/**
 * @sw-package fundamentals@after-sales
 */
import ImportExportProfileMappingService from 'src/module/sw-import-export/service/importExportProfileMapping.service';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import * as mappings from './mocks/mappings.mock';
import withRequiredProductType from 'src/../test/_helper_/withRequiredProductType';

const requiredProductMappingsWithType = [
    ...mappings.productProfileOnlyRequired,
    { key: 'type', mappedKey: 'type' },
];

describe('module/sw-import-export/service/importExportProfileMapping.service.spec.js', () => {
    let importExportProfileMappingService;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(
            ([
                entityName,
                entityDefinition,
            ]) => {
                Shopware.EntityDefinition.add(entityName, entityDefinition);
            },
        );

        importExportProfileMappingService = new ImportExportProfileMappingService(Shopware.EntityDefinition);
    });

    it('should contain all public functions', async () => {
        expect(typeof importExportProfileMappingService.validate).toBe('function');
    });

    // Guards the assumption withRequiredProductType() relies on: the entity schema mock is a 6.7
    // snapshot in which product.type is optional. Once the mock is regenerated from a v6.8 instance
    // this fails, and the v6.8 variants below should assert against the real schema instead.
    it('pins the product type flag in the entity schema mock', () => {
        expect(Shopware.EntityDefinition.get('product').properties.type.flags.required).toBeUndefined();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should not find any missing required fields', () => {
        const violations = importExportProfileMappingService.validate('product', mappings.productProfileOnlyRequired);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should not find any missing required fields',
        withRequiredProductType(() => {
            const violations = importExportProfileMappingService.validate('product', requiredProductMappingsWithType);

            expect(violations.missingRequiredFields).toHaveLength(0);
            expect(violations.duplicateMappings).toHaveLength(0);
        }),
    );

    [
        'id',
        'versionId',
        'parentVersionId',
        'stock',
        'productManufacturerVersionId',
        'taxId',
        'productNumber',
    ].forEach((fieldName) => {
        // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
        it.deprecated('v6.8.0.0')(`product: should find missing required field ${fieldName}`, () => {
            const mapping = mappings.productProfileOnlyRequired.filter((field) => field.key !== fieldName);
            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain(fieldName);
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            `product: should find missing required field ${fieldName}`,
            withRequiredProductType(() => {
                const mapping = requiredProductMappingsWithType.filter((field) => field.key !== fieldName);
                const violations = importExportProfileMappingService.validate('product', mapping);

                expect(violations.missingRequiredFields).toHaveLength(1);
                expect(violations.duplicateMappings).toHaveLength(0);

                expect(violations.missingRequiredFields).toContain(fieldName);
            }),
        );
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should find missing required field name', () => {
        const mapping = mappings.productProfileOnlyRequired.filter((field) => field.key !== 'translations.DEFAULT.name');
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('name');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should find missing required field name',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter((field) => field.key !== 'translations.DEFAULT.name');
            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('name');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should find missing required field createdAt', () => {
        const mapping = mappings.productProfileOnlyRequired.filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should find missing required field createdAt',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter(
                (field) => field.key !== 'translations.DEFAULT.createdAt',
            );
            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('createdAt');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should return all missing required fields', () => {
        const violations = importExportProfileMappingService.validate('product', []);

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields.sort()).toEqual(
            [
                'id',
                'versionId',
                'parentVersionId',
                'productManufacturerVersionId',
                'productMediaVersionId',
                'taxId',
                'productNumber',
                'stock',
                'name',
                'canonicalProductVersionId',
                'cmsPageVersionId',
                'createdAt',
            ].sort(),
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should return all missing required fields',
        withRequiredProductType(() => {
            const violations = importExportProfileMappingService.validate('product', []);

            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields.sort()).toEqual(
                [
                    'id',
                    'versionId',
                    'parentVersionId',
                    'productManufacturerVersionId',
                    'productMediaVersionId',
                    'taxId',
                    'productNumber',
                    'stock',
                    'name',
                    'canonicalProductVersionId',
                    'cmsPageVersionId',
                    'createdAt',
                    'type',
                ].sort(),
            );
        }),
    );

    it('product: should find missing required when parentProduct is existing', async () => {
        const mapping = mappings.productDuplicateProfileOnlyRequired.filter((field) => field.key === 'productNumber');
        const violations = importExportProfileMappingService.validate(
            'product',
            mapping,
            mappings.productDuplicateProfileOnlyRequired,
        );

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toEqual([
            'id',
            'taxId',
        ]);
    });

    it('product: should not find any missing required when parentProduct is existing', async () => {
        const violations = importExportProfileMappingService.validate(
            'product',
            mappings.productDuplicateProfileOnlyRequired,
            mappings.productDuplicateProfileOnlyRequired,
        );

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    it('product: should find missing required when key.id is existing', async () => {
        const violations = importExportProfileMappingService.validate(
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

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toEqual([
            'id',
            'productNumber',
        ]);
    });

    it('media: should not find any missing required fields', async () => {
        const violations = importExportProfileMappingService.validate('media', mappings.mediaProfileOnlyRequired);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field id', () => {
        const mapping = mappings.productProfileOnlyRequired.filter((field) => field.key !== 'id');

        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('id');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field id',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter((field) => field.key !== 'id');

            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('id');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field createdAt', () => {
        const mapping = mappings.productProfileOnlyRequired.filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field createdAt',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter(
                (field) => field.key !== 'translations.DEFAULT.createdAt',
            );
            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('createdAt');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('should detect duplicate mapping keys', () => {
        const mapping = [
            ...mappings.productProfileOnlyRequired,
            mappings.productProfileOnlyRequired.find((mapping) => mapping.key === 'id'),
        ];

        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(1);

        expect(violations.duplicateMappings.at(0).key).toBe('id');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should detect duplicate mapping keys',
        withRequiredProductType(() => {
            const mapping = [
                ...requiredProductMappingsWithType,
                requiredProductMappingsWithType.find((mapping) => mapping.key === 'id'),
            ];

            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(0);
            expect(violations.duplicateMappings).toHaveLength(1);

            expect(violations.duplicateMappings.at(0).key).toBe('id');
        }),
    );

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

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product_cross_selling: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product_cross_selling', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'position',
            'type',
            'product.id',
            'product.price.DEFAULT.net',
            'product.price.DEFAULT.gross',
            'product.productNumber',
            'product.stock',
            'product.tax.id',
            'product.tax.taxRate',
            'product.tax.name',
            'product.tax.position',
            'product.translations.DEFAULT.name',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product_cross_selling: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
                'product_cross_selling',
                3,
            );

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'position',
                'type',
                'product.id',
                'product.price.DEFAULT.net',
                'product.price.DEFAULT.gross',
                'product.productNumber',
                'product.stock',
                'product.tax.id',
                'product.tax.taxRate',
                'product.tax.name',
                'product.tax.position',
                'product.translations.DEFAULT.name',
                'product.type',
                'translations.DEFAULT.name',
            ]);
        }),
    );

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
            'language.active',
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
            'salesChannel.paymentMethod.technicalName',
            'salesChannel.paymentMethod.translations.DEFAULT.name',
            'salesChannel.shippingMethod.id',
            'salesChannel.shippingMethod.technicalName',
            'salesChannel.shippingMethod.taxType',
            'salesChannel.shippingMethod.deliveryTime.id',
            'salesChannel.shippingMethod.translations.DEFAULT.name',
            'salesChannel.country.id',
            'salesChannel.country.isEu',
            'salesChannel.country.translations.DEFAULT.name',
            'salesChannel.country.translations.DEFAULT.addressFormat',
            'salesChannel.navigationCategory.id',
            'salesChannel.navigationCategory.displayNestedProducts',
            'salesChannel.navigationCategory.type',
            'salesChannel.navigationCategory.productAssignmentType',
            'salesChannel.navigationCategory.translations.DEFAULT.name',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should list all required fields with depth 1', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'stock',
            'tax.id',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should list all required fields with depth 1',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 1);

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'price.DEFAULT.net',
                'price.DEFAULT.gross',
                'productNumber',
                'stock',
                'tax.id',
                'translations.DEFAULT.name',
                'type',
            ]);
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'stock',
            'tax.id',
            'tax.taxRate',
            'tax.name',
            'tax.position',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 3);

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'price.DEFAULT.net',
                'price.DEFAULT.gross',
                'productNumber',
                'stock',
                'tax.id',
                'tax.taxRate',
                'tax.name',
                'tax.position',
                'translations.DEFAULT.name',
                'type',
            ]);
        }),
    );

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
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
            'product_configurator_setting',
            1,
        );

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'product.id',
            'option.id',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product_configurator_setting: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
            'product_configurator_setting',
            3,
        );

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'product.id',
            'product.price.DEFAULT.net',
            'product.price.DEFAULT.gross',
            'product.productNumber',
            'product.stock',
            'product.tax.id',
            'product.tax.taxRate',
            'product.tax.name',
            'product.tax.position',
            'product.translations.DEFAULT.name',
            'option.id',
            'option.group.id',
            'option.group.displayType',
            'option.group.sortingType',
            'option.group.translations.DEFAULT.name',
            'option.translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'product_configurator_setting: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
                'product_configurator_setting',
                3,
            );

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'product.id',
                'product.price.DEFAULT.net',
                'product.price.DEFAULT.gross',
                'product.productNumber',
                'product.stock',
                'product.tax.id',
                'product.tax.taxRate',
                'product.tax.name',
                'product.tax.position',
                'product.translations.DEFAULT.name',
                'product.type',
                'option.id',
                'option.group.id',
                'option.group.displayType',
                'option.group.sortingType',
                'option.group.translations.DEFAULT.name',
                'option.translations.DEFAULT.name',
            ]);
        }),
    );
});
