import './component';
import './config';
import './preview';

/**
 * @private since v6.5.0
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'category-navigation',
    label: 'sw-cms.elements.sidebarCategoryNavigation.label',
    component: 'sw-cms-el-category-navigation',
    configComponent: 'sw-cms-el-config-category-navigation',
    previewComponent: 'sw-cms-el-preview-category-navigation',
    disabledConfigInfoTextKey: 'sw-cms.elements.sidebarCategoryNavigation.infoText.navigationElement',
});
