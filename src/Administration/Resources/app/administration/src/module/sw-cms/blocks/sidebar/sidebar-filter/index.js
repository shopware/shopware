import CMS from '../../../constant/sw-cms.constant';

import './component';
import './preview';

/**
 * @private since v6.5.0
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'sidebar-filter',
    label: 'sw-cms.blocks.sidebar.sidebarFilter.label',
    category: 'sidebar',
    component: 'sw-cms-block-preview-sidebar-filter',
    previewComponent: 'sw-cms-block-preview-sidebar-filter',
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
