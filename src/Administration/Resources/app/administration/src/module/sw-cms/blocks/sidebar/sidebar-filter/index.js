import './component';
import './preview';

const { Application } = Shopware;

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'sidebar-filter',
    label: 'sw-cms.blocks.sidebar.sidebarFilter.label',
    category: 'sidebar',
    component: 'sw-cms-block-preview-sidebar-filter',
    previewComponent: 'sw-cms-block-preview-sidebar-filter',
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
