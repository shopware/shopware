/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-popup', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-popup', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'popup',
    label: 'sw-cms.blocks.popup.popup.label', // Changed from form to popup
    category: 'form', // Could be changed to 'custom' or another relevant category
    component: 'sw-cms-block-popup',
    previewComponent: 'sw-cms-preview-popup',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'popup-element',
            default: {
                config: {
                    title: {
                        source: 'static',
                        value: 'Popup Title',
                    },
                    content: {
                        source: 'static',
                        value: 'This is a popup content',
                    },
                    buttonText: {
                        source: 'static',
                        value: 'Open Popup',
                    },
                    width: {
                        source: 'static',
                        value: '400px',
                    },
                    height: {
                        source: 'static',
                        value: 'auto',
                    },
                    trigger: {
                        source: 'static',
                        value: 'click',
                    },
                },
            },
        },
    },
});