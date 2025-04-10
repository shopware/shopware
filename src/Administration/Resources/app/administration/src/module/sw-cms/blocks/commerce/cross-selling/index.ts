/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-cross-selling', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-cross-selling', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'cross-selling',
    label: 'sw-cms.blocks.commerce.crossSelling.label',
    category: 'commerce',
    component: 'sw-cms-block-cross-selling',
    previewComponent: 'sw-cms-preview-cross-selling',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'cross-selling',
    },
});
