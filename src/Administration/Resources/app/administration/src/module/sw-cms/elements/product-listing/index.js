/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-preview-product-listing', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-config-product-listing', () => import('./config'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-product-listing', () => import('./component'));
/**
 * @private
 * @package content
 */
Shopware.Component.register(
    'sw-cms-el-config-product-listing-config-sorting-grid',
    () => import('./config/components/sw-cms-el-config-product-listing-config-sorting-grid'),
);

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-listing',
    label: 'sw-cms.elements.productListing.label',
    hidden: true,
    removable: false,
    component: 'sw-cms-el-product-listing',
    previewComponent: 'sw-cms-el-preview-product-listing',
    configComponent: 'sw-cms-el-config-product-listing',
    defaultConfig: {
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        showSorting: {
            source: 'static',
            value: true,
        },
        useCustomSorting: {
            source: 'static',
            value: false,
        },
        availableSortings: {
            source: 'static',
            value: {},
        },
        defaultSorting: {
            source: 'static',
            value: '',
        },
        filters: {
            source: 'static',
            value: 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
        },
        // eslint-disable-next-line inclusive-language/use-inclusive-words
        propertyWhitelist: {
            source: 'static',
            value: [],
        },
    },
});
