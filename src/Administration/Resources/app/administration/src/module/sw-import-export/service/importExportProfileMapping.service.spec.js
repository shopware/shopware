/**
 * @sw-package fundamentals@after-sales
 */
import ImportExportProfileMappingService from 'src/module/sw-import-export/service/importExportProfileMapping.service';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import * as mappings from './mocks/mappings.mock';

// CHANGE REASON: Feature-scoped tests must mirror the generated v6.8 product schema and restore the shared definition. @upgraded
function withRequiredProductType(callback) {
    return async () => {
        const typeFlags = Shopware.EntityDefinition.get('product').properties.type.flags;
        const previousRequired = typeFlags.required;

        typeFlags.required = true;

        try {
            await callback();
        } finally {
            if (previousRequired === undefined) {
                delete typeFlags.required;
            } else {
                typeFlags.required = previousRequired;
            }
        }
    };
}

// CHANGE REASON: The v6.8 product mapping fixture includes the newly required product type. @upgraded
function getRequiredProductMappings(withProductType = false) {
    return [
        ...mappings.productProfileOnlyRequired,
        ...(withProductType ? [{ key: 'type', mappedKey: 'type' }] : []),
    ];
}

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

    const findsNoMissingRequiredProductFields = (withProductType = false) => {
        const violations = importExportProfileMappingService.validate(
            'product',
            getRequiredProductMappings(withProductType),
        );

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    };

    // CHANGE REASON: Product type remains optional before v6.8. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should not find any missing required fields', () =>
        findsNoMissingRequiredProductFields(),
    );

    // CHANGE REASON: The v6.8 mapping supplies the newly required product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should not find any missing required fields',
        withRequiredProductType(() => findsNoMissingRequiredProductFields(true)),
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
        const findsMissingRequiredProductField = (withProductType = false) => {
            const mapping = getRequiredProductMappings(withProductType).filter((field) => field.key !== fieldName);
            const violations = importExportProfileMappingService.validate('product', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain(fieldName);
        };

        // CHANGE REASON: Product type remains optional before v6.8. @removed @upgraded
        // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
        it.deprecated('v6.8.0.0')(`product: should find missing required field ${fieldName}`, () =>
            findsMissingRequiredProductField(),
        );

        // CHANGE REASON: The v6.8 mapping supplies product type while isolating the requested missing field. @upgraded
        it.activeFeatureFlags(['v6.8.0.0'])(
            `product: should find missing required field ${fieldName}`,
            withRequiredProductType(() => findsMissingRequiredProductField(true)),
        );
    });

    const findsMissingRequiredProductName = (withProductType = false) => {
        const mapping = getRequiredProductMappings(withProductType).filter(
            (field) => field.key !== 'translations.DEFAULT.name',
        );
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('name');
    };

    // CHANGE REASON: Product type remains optional before v6.8. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should find missing required field name', () => findsMissingRequiredProductName());

    // CHANGE REASON: The v6.8 mapping supplies product type while isolating the missing name. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should find missing required field name',
        withRequiredProductType(() => findsMissingRequiredProductName(true)),
    );

    const findsMissingRequiredProductCreatedAt = (withProductType = false) => {
        const mapping = getRequiredProductMappings(withProductType).filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    };

    // CHANGE REASON: Product type remains optional before v6.8. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('product: should find missing required field createdAt', () =>
        findsMissingRequiredProductCreatedAt(),
    );

    // CHANGE REASON: The v6.8 mapping supplies product type while isolating the missing creation date. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should find missing required field createdAt',
        withRequiredProductType(() => findsMissingRequiredProductCreatedAt(true)),
    );

    const returnsAllMissingRequiredProductFields = (withProductType = false) => {
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
                ...(withProductType ? ['type'] : []),
            ].sort(),
        );
    };

    // CHANGE REASON: The legacy required-field list excludes the optional product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should return all missing required fields', () =>
        returnsAllMissingRequiredProductFields(),
    );

    // CHANGE REASON: The v6.8 required-field list includes product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should return all missing required fields',
        withRequiredProductType(() => returnsAllMissingRequiredProductFields(true)),
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

    const findsMissingRequiredMediaId = (withProductType = false) => {
        const mapping = getRequiredProductMappings(withProductType).filter((field) => field.key !== 'id');

        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('id');
    };

    // CHANGE REASON: This legacy product mapping fixture predates the required product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field id', () => findsMissingRequiredMediaId());

    // CHANGE REASON: The v6.8 mapping supplies product type while isolating the missing id. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field id',
        withRequiredProductType(() => findsMissingRequiredMediaId(true)),
    );

    const findsMissingRequiredMediaCreatedAt = (withProductType = false) => {
        const mapping = getRequiredProductMappings(withProductType).filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    };

    // CHANGE REASON: This legacy product mapping fixture predates the required product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field createdAt', () =>
        findsMissingRequiredMediaCreatedAt(),
    );

    // CHANGE REASON: The v6.8 mapping supplies product type while isolating the missing creation date. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field createdAt',
        withRequiredProductType(() => findsMissingRequiredMediaCreatedAt(true)),
    );

    const detectsDuplicateMappingKeys = (withProductType = false) => {
        const requiredProductMappings = getRequiredProductMappings(withProductType);
        const mapping = [
            ...requiredProductMappings,
            requiredProductMappings.find((mapping) => mapping.key === 'id'),
        ];

        const violations = importExportProfileMappingService.validate('product', mapping);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(1);

        expect(violations.duplicateMappings.at(0).key).toBe('id');
    };

    // CHANGE REASON: The legacy duplicate mapping fixture predates the required product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type mapping.
    it.deprecated('v6.8.0.0')('should detect duplicate mapping keys', () => detectsDuplicateMappingKeys());

    // CHANGE REASON: The v6.8 duplicate mapping fixture also supplies the required product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should detect duplicate mapping keys',
        withRequiredProductType(() => detectsDuplicateMappingKeys(true)),
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

    const listsProductCrossSellingRequiredFieldsAtDepthThree = (withProductType = false) => {
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
            ...(withProductType ? ['product.type'] : []),
            'translations.DEFAULT.name',
        ]);
    };

    // CHANGE REASON: The legacy nested product field list excludes the optional product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product_cross_selling: should list all required fields with depth 3', () =>
        listsProductCrossSellingRequiredFieldsAtDepthThree(),
    );

    // CHANGE REASON: The v6.8 nested product field list includes product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product_cross_selling: should list all required fields with depth 3',
        withRequiredProductType(() => listsProductCrossSellingRequiredFieldsAtDepthThree(true)),
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

    const listsProductRequiredFieldsAtDepthOne = (withProductType = false) => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('product', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'stock',
            'tax.id',
            'translations.DEFAULT.name',
            ...(withProductType ? ['type'] : []),
        ]);
    };

    // CHANGE REASON: The legacy product field list excludes the optional product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should list all required fields with depth 1', () =>
        listsProductRequiredFieldsAtDepthOne(),
    );

    // CHANGE REASON: The v6.8 product field list includes product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should list all required fields with depth 1',
        withRequiredProductType(() => listsProductRequiredFieldsAtDepthOne(true)),
    );

    const listsProductRequiredFieldsAtDepthThree = (withProductType = false) => {
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
            ...(withProductType ? ['type'] : []),
        ]);
    };

    // CHANGE REASON: The legacy product field list excludes the optional product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product: should list all required fields with depth 3', () =>
        listsProductRequiredFieldsAtDepthThree(),
    );

    // CHANGE REASON: The v6.8 product field list includes product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product: should list all required fields with depth 3',
        withRequiredProductType(() => listsProductRequiredFieldsAtDepthThree(true)),
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

    const listsProductConfiguratorRequiredFieldsAtDepthThree = (withProductType = false) => {
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
            ...(withProductType ? ['product.type'] : []),
            'option.id',
            'option.group.id',
            'option.group.displayType',
            'option.group.sortingType',
            'option.group.translations.DEFAULT.name',
            'option.translations.DEFAULT.name',
        ]);
    };

    // CHANGE REASON: The legacy nested product field list excludes the optional product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('product_configurator_setting: should list all required fields with depth 3', () =>
        listsProductConfiguratorRequiredFieldsAtDepthThree(),
    );

    // CHANGE REASON: The v6.8 nested product field list includes product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'product_configurator_setting: should list all required fields with depth 3',
        withRequiredProductType(() => listsProductConfiguratorRequiredFieldsAtDepthThree(true)),
    );
});
