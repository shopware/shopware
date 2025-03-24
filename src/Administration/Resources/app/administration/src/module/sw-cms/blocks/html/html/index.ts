/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-html', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-html', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'html',
    label: 'sw-cms.blocks.html.html.label',
    category: 'html',
    component: 'sw-cms-block-html',
    previewComponent: 'sw-cms-preview-html',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'html',
    },
});
