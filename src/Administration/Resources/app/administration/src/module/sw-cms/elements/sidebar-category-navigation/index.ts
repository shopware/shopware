/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-category-navigation', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-category-navigation', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-category-navigation', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'category-navigation',
    label: 'sw-cms.elements.sidebarCategoryNavigation.label',
    component: 'sw-cms-el-category-navigation',
    configComponent: 'sw-cms-el-config-category-navigation',
    previewComponent: 'sw-cms-el-preview-category-navigation',
    disabledConfigInfoTextKey: 'sw-cms.elements.sidebarCategoryNavigation.infoText.navigationElement',
});
