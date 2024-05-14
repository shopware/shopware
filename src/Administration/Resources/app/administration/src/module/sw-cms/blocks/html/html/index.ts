/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-block-html', () => import('./component'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-preview-html', () => import('./preview'));

/**
 * @private
 * @package buyers-experience
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
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'html',
    },
});
