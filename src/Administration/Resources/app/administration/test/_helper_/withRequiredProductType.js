/**
 * @sw-package framework
 */

/**
 * Runs `callback` with `product.type` flagged as required in the entity schema.
 *
 * The entity schema mock is dumped before Jest enables feature flags for individual tests. In the
 * regular unit-test run, it therefore contains the legacy optional field while the v6.8 tests run
 * with the feature enabled. Simulate the required flag for those tests.
 *
 * @private
 */
export default function withRequiredProductType(callback) {
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
