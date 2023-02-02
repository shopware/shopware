/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-preview-form', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-config-form', () => import('./config'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-form', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'form',
    label: 'sw-cms.elements.form.label',
    component: 'sw-cms-el-form',
    configComponent: 'sw-cms-el-config-form',
    previewComponent: 'sw-cms-el-preview-form',
    defaultConfig: {
        type: {
            source: 'static',
            value: 'contact',
        },
        title: {
            source: 'static',
            value: '',
        },
        mailReceiver: {
            source: 'static',
            value: [],
        },
        defaultMailReceiver: {
            source: 'static',
            value: true,
        },
        confirmationText: {
            source: 'static',
            value: '',
        },
    },
});
