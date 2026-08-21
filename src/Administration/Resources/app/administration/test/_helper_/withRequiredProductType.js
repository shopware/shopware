/**
 * @sw-package framework
 */

/**
 * Runs `callback` with `product.type` flagged as required in the entity schema.
 *
 * The entity schema mock is a single version-agnostic snapshot in which `product.type` is
 * optional, so tests for the v6.8 schema have to simulate the flag. `pins the product type flag
 * in the entity schema mock` guards that assumption — once the mock is regenerated from a v6.8
 * instance, drop this helper and assert against the real schema instead.
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
