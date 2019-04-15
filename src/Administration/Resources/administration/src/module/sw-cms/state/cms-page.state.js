import { State } from 'src/core/shopware';

State.registerStore('cmsPageState', {
    currentPage: null,
    currentMappingEntity: null,
    currentMappingTypes: {},
    currentDemoEntity: null,
    pageEntityName: 'cms_page',
    defaultMediaFolderId: null
});
