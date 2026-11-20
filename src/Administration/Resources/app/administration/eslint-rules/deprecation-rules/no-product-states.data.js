/**
 * @sw-package framework
 *
 * The product states that 6.8 removes in favor of the product type, taken from the `@deprecated tag:v6.8.0` markers
 * and the `v6.8.0.0` branches of the product and product stream modules. A list of states becomes one type:
 * `is-physical` → `physical`, `is-download` → `digital`.
 *
 * - `members`: names of components and the `swProductDetail` store. The unique ones are also marked on other
 *   objects (`item.productStates`), as object keys (`query: { creationStates }`) and as template attributes.
 * - `literals`: filter keys, field types and condition types.
 * - `stateValues`: the state values; not marked on a line that already has a product states finding.
 * - `statesProperty`: `.states` on an object named like a product or variant, or inside overrides of
 *   `sw-product*` components (`sw-product-stream*` included). `country.states` and similar stay unmarked.
 */

const MAPPING = 'is-physical → physical, is-download → digital';

module.exports = {
    id: 'product-states',
    description: 'Marks product states, which 6.8 replaces with the product type',
    members: [
        { name: 'productStates', unique: true, message: `productStates is removed. Use productType, one type instead of a list: this.productType === 'digital' (${MAPPING}).` },
        { name: 'creationStates', unique: true, message: `creationStates is removed. Use creationType, one type instead of a list, also as query of the route sw.product.create (${MAPPING}).` },
        { name: 'currentProductStates', unique: true, message: `sw-product-detail-variants.currentProductStates is removed. Use currentProductType, one type instead of a list (${MAPPING}).` },
        { name: 'productStateOptions', unique: true, message: 'sw-product-stream-value.productStateOptions is removed. Use productTypeOptions with the values physical and digital.' },
        { component: 'sw-product-list', name: 'productTypeOptions', message: "sw-product-list.productTypeOptions is removed. Use the productTypes computed (['physical', 'digital'])." },
    ],
    literals: [
        { value: 'product-states-filter', message: 'The product list filter product-states-filter is removed. Use product-type-filter, which filters the property type.' },
        { value: 'product_state_list', message: 'The product stream field type product_state_list is removed. Use product_type.' },
        { value: 'cartLineItemProductStates', message: 'The rule condition cartLineItemProductStates is removed. Use cartLineItemProductType.' },
        { value: 'product.states', message: "The product field states is removed. Use type ('physical' or 'digital')." },
    ],
    stateValues: [
        { value: 'is-physical', message: "The product state 'is-physical' is removed. Use the product type 'physical' (product.type, productType, creationType)." },
        { value: 'is-download', message: "The product state 'is-download' is removed. Use the product type 'digital' (product.type, productType, creationType)." },
    ],
    statesProperty: {
        objects: /(product|variant)$/i,
        targetPrefix: 'sw-product',
        exclude: /countr|machine/i,
        message: `product.states is removed. Use product.type, one type instead of a list (${MAPPING}).`,
    },
};
