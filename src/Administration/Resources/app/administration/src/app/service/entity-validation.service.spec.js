/**
 * @sw-package framework
 */
import EntityValidationService from 'src/app/service/entity-validation.service';
import EntityFactory from 'src/core/data/entity-factory.data';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import ErrorResolver from 'src/core/data/error-resolver.data';
import EntityDefinition from 'src/core/data/entity-definition.data';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

function createService() {
    return new EntityValidationService(EntityDefinitionFactory, new ChangesetGenerator(), new ErrorResolver());
}

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

const entityFactory = new EntityFactory();

describe('src/app/service/entity-validation.service.js', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(
            ([
                entityName,
                definitionData,
            ]) => {
                Shopware.EntityDefinition.add(entityName, new EntityDefinition(definitionData));
            },
        );
    });

    it('should create a required shopware error with the right error code and source pointer', () => {
        const fieldPointer = '/0/name';
        const error = EntityValidationService.createRequiredError(fieldPointer);

        expect(error).toEqual({
            code: EntityValidationService.ERROR_CODE_REQUIRED,
            source: {
                pointer: fieldPointer,
            },
        });
    });

    const validatesEmptyProduct = (requiresProductType = false) => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('product');

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/taxId' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/price' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/productNumber' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/stock' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/name' },
            },
            ...(requiresProductType
                ? [
                      {
                          code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                          source: { pointer: '/0/type' },
                      },
                  ]
                : []),
        ]);
    };

    // CHANGE REASON: The legacy product schema does not require a product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('should validate an empty product and report errors', () => validatesEmptyProduct());

    // CHANGE REASON: The v6.8 product schema reports the newly required product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should validate an empty product and report errors',
        withRequiredProductType(() => validatesEmptyProduct(true)),
    );

    const validatesMissingProductPrice = (withProductType = false) => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('product');
        testEntity.name = 'MyProductName';
        testEntity.stock = 5;
        testEntity.productNumber = 'MyProductNumber';
        testEntity.taxId = 'some-tax-uuid';
        if (withProductType) {
            testEntity.type = 'physical';
        }
        testEntity.price = [
            {
                gross: null,
                net: null,
            },
        ];

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/price/0/net' },
            },
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/price/0/gross' },
            },
        ]);
    };

    // CHANGE REASON: The legacy fixture is complete without a product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('should validate missing price for a product', () => validatesMissingProductPrice());

    // CHANGE REASON: The v6.8 fixture supplies the required product type so validation remains focused on prices. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should validate missing price for a product',
        withRequiredProductType(() => validatesMissingProductPrice(true)),
    );

    const validatesCompleteProduct = (withProductType = false) => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('product');
        testEntity.name = 'MyProductName';
        testEntity.stock = 5;
        testEntity.productNumber = 'MyProductNumber';
        testEntity.taxId = 'some-tax-uuid';
        if (withProductType) {
            testEntity.type = 'physical';
        }
        testEntity.price = [
            {
                gross: 10,
                net: 10,
            },
        ];

        // validate should return right result
        const isValid = service.validate(testEntity);
        expect(isValid).toBe(true);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([]);
    };

    // CHANGE REASON: The legacy fixture is complete without a product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('should validate a complete product and report no errors', () => validatesCompleteProduct());

    // CHANGE REASON: The v6.8 complete fixture includes the newly required product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should validate a complete product and report no errors',
        withRequiredProductType(() => validatesCompleteProduct(true)),
    );

    const validatesProductCallbackErrors = (withProductType = false) => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('product');
        testEntity.name = 'MyProductName';
        testEntity.stock = 5;
        testEntity.productNumber = 'MyProductNumber';
        testEntity.taxId = 'some-tax-uuid';
        if (withProductType) {
            testEntity.type = 'physical';
        }
        testEntity.price = [
            {
                gross: 10,
                net: 10,
            },
        ];

        const customValidator = jest.fn((errors, product) => {
            // custom download product validation
            if (product.downloads === undefined || product.downloads.length < 1) {
                errors.push(EntityValidationService.createRequiredError('/0/downloads'));
            }

            return errors;
        });

        const expectedErrors = [
            {
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                source: { pointer: '/0/downloads' },
            },
        ];

        // validate should return right result
        const isValid = service.validate(testEntity, customValidator);
        expect(isValid).toBe(false);

        // found errors should match
        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual(expectedErrors);

        // custom validator should have been called with the right arguments
        expect(customValidator.mock.calls).toHaveLength(1);
        expect(customValidator.mock.calls[0][0]).toEqual(expectedErrors); // initial errors already modified because of array reference
        expect(customValidator.mock.calls[0][1]).toBe(testEntity); // entity
        expect(customValidator.mock.calls[0][2]).toBe(Shopware.EntityDefinition.get(testEntity.getEntityName())); // entity definition
        expect(customValidator.mock.results[0].value).toEqual(expectedErrors); // should return the errors
    };

    // CHANGE REASON: The legacy fixture isolates callback errors without a product type. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('should validate a product and report callback errors', () =>
        validatesProductCallbackErrors(),
    );

    // CHANGE REASON: The v6.8 fixture supplies the required type so the callback error remains the only violation. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should validate a product and report callback errors',
        withRequiredProductType(() => validatesProductCallbackErrors(true)),
    );

    const validatesCompleteProductWithIgnoredFields = (withProductType = false) => {
        const service = createService();
        service.errorResolver.handleWriteErrors = jest.fn(() => undefined);
        const testEntity = entityFactory.create('product');
        testEntity.name = null;
        testEntity.stock = 5;
        testEntity.productNumber = 'MyProductNumber';
        testEntity.taxId = 'some-tax-uuid';
        if (withProductType) {
            testEntity.type = 'physical';
        }
        testEntity.price = [
            {
                gross: 10,
                net: 10,
            },
        ];

        const ignoreFields = ['name'];

        const isValid = service.validate(testEntity, undefined, ignoreFields);
        expect(isValid).toBe(true);

        expect(service.errorResolver.handleWriteErrors.mock.calls).toHaveLength(1);
        expect(service.errorResolver.handleWriteErrors.mock.calls[0][1].errors).toEqual([]);
    };

    // CHANGE REASON: The legacy fixture is complete without a product type when name is ignored. @removed @upgraded
    // @deprecated tag:v6.8.0.0 - The test will be removed with the optional product type schema.
    it.deprecated('v6.8.0.0')('should validate a complete product with ignore fields and report no errors', () =>
        validatesCompleteProductWithIgnoredFields(),
    );

    // CHANGE REASON: The v6.8 ignored-fields fixture includes the newly required product type. @upgraded
    it.activeFeatureFlags(['v6.8.0.0'])(
        'should validate a complete product with ignore fields and report no errors',
        withRequiredProductType(() => validatesCompleteProductWithIgnoredFields(true)),
    );
});
