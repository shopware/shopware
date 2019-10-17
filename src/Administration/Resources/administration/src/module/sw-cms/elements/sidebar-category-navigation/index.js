import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'category-navigation',
    label: 'Category Navigation',
    component: 'sw-cms-el-category-navigation',
    configComponent: 'sw-cms-el-config-category-navigation',
    previewComponent: 'sw-cms-el-preview-category-navigation',
    disabledConfigInfoTextKey: 'sw-cms.elements.infoTextNavigationElement'
});
