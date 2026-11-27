/**
 * @sw-package framework
 *
 * The custom-field set loader computeds that 6.8 removes in favor of the shared
 * `customFieldDataProviderService.getCustomFieldSets(entityName)`, with the entity name of every component.
 * Taken from the `@deprecated tag:v6.8.0` markers. The names are generic and stay in other components, so only
 * `customFieldSetLandingPageCriteria` is marked without a known target.
 */

const loader = (component, name, entity) => ({
    component,
    name,
    message: `${component}.${name} is removed. Use Shopware.Service('customFieldDataProviderService').getCustomFieldSets('${entity}').`,
});

module.exports = {
    id: 'custom-field-set-loaders',
    description: 'Marks the custom-field set loader computeds, which 6.8 replaces with customFieldDataProviderService',
    members: [
        loader('sw-bulk-edit-product', 'customFieldSetRepository', 'product'),
        loader('sw-bulk-edit-product', 'customFieldSetCriteria', 'product'),
        loader('sw-bulk-edit-order', 'customFieldSetRepository', 'order'),
        loader('sw-bulk-edit-order', 'customFieldSetCriteria', 'order'),
        loader('sw-bulk-edit-customer', 'customFieldSetRepository', 'customer'),
        loader('sw-bulk-edit-customer', 'customFieldSetCriteria', 'customer'),
        loader('sw-settings-units-detail', 'customFieldSetRepository', 'unit'),
        loader('sw-settings-units-detail', 'customFieldSetCriteria', 'unit'),
        loader('sw-category-detail', 'customFieldSetRepository', 'category'),
        loader('sw-category-detail', 'customFieldSetCriteria', 'category'),
        { ...loader('sw-category-detail', 'customFieldSetLandingPageCriteria', 'landing_page'), unique: true },
        loader('sw-customer-detail-base', 'customFieldSetRepository', 'customer'),
        loader('sw-customer-detail-base', 'customFieldSetCriteria', 'customer'),
        loader('sw-customer-detail-addresses', 'customFieldSetRepository', 'customer_address'),
        loader('sw-manufacturer-detail', 'customFieldSetRepository', 'product_manufacturer'),
        loader('sw-manufacturer-detail', 'customFieldSetCriteria', 'product_manufacturer'),
        loader('sw-order-detail-details', 'customFieldSetRepository', 'order'),
        loader('sw-order-detail-details', 'customFieldSetCriteria', 'order'),
        loader('sw-sales-channel-detail', 'customFieldRepository', 'sales_channel'),
        { component: 'sw-product-stream-detail', name: 'customFieldSetRepository', message: "sw-product-stream-detail.customFieldSetRepository is removed. Use the customFieldRepository computed for custom fields, or Shopware.Service('customFieldDataProviderService').getCustomFieldSets('product_stream') for the sets." },
    ],
};
