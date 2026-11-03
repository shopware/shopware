import './component/register';
import './config/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'sidebar-filter',
    label: 'sw-cms.elements.sidebarFilter.label',
    component: 'sw-cms-el-sidebar-filter',
    configComponent: 'sw-cms-el-config-sidebar-filter',
    previewComponent: 'sw-cms-el-preview-sidebar-filter',
    allowedPageTypes: [Shopware.Constants.CMS.PAGE_TYPES.LISTING],
    disabledConfigInfoTextKey: 'sw-cms.elements.sidebarFilter.infoText.filterElement',
});
