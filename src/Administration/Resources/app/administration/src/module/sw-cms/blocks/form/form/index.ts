/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-form', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-form', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'form',
    label: 'sw-cms.blocks.form.form.label',
    category: 'form',
    component: 'sw-cms-block-form',
    previewComponent: 'sw-cms-preview-form',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'form',
        },
    },
});
