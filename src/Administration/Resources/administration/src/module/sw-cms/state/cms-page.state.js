const { State } = Shopware;

State.registerStore('cmsPageState', {
    currentPage: null,
    currentMappingEntity: null,
    currentMappingTypes: {},
    currentDemoEntity: null,
    pageEntityName: 'cms_page',
    defaultMediaFolderId: null,
    currentCmsDeviceView: 'desktop'
});
