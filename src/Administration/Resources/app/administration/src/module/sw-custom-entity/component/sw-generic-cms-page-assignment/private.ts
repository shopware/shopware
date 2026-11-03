import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-generic-cms-page-assignment',
    load: () => import('./src/sw-generic-cms-page-assignment'),
    public: {
        props: [
            'cmsPageId',
            'slotOverrides',
            'allowedPageTypes',
        ],
        emits: [
            'update:cms-page-id',
            'create-layout',
            'update:slot-overrides',
        ],
        blocks: [
            'sw_generic_cms_page_assignment',
        ],
        computed: [
            'cmsPageRepository',
            'changesetGenerator',
            'cmsPageCriteria',
            'pageTypeTitle',
        ],
        methods: [
            'openLayoutModal',
            'closeLayoutModal',
            'onLayoutSelect',
            'openInCmsEditor',
            'createNewLayout',
            'applySlotOverrides',
            'getCmsPage',
            'deleteSpecificKeys',
            'emitCmsPageOverrides',
        ],
    },
});
