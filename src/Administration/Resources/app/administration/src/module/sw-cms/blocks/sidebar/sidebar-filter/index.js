import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-sidebar-filter', () => import('./preview'));
/**
  * @private
  */
Shopware.Component.register('sw-cms-block-sidebar-filter', () => import('./component'));


/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'sidebar-filter',
    label: 'sw-cms.blocks.sidebar.sidebarFilter.label',
    category: 'sidebar',
    component: 'sw-cms-block-sidebar-filter',
    previewComponent: 'sw-cms-preview-sidebar-filter',
    allowedPageTypes: [CMS.PAGE_TYPES.LISTING],
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'sidebar-filter',
    },
});
