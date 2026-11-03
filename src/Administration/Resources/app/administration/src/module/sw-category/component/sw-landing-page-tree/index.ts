import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-landing-page-tree',
    load: () => import('./src/sw-landing-page-tree'),
    public: {
        props: [
            'landingPageId',
            'currentLanguageId',
            'allowEdit',
            'allowCreate',
            'allowDelete',
        ],
        emits: [
            'landing-page-checked-elements-count',
            'unsaved-changes',
        ],
        blocks: [
            'sw_landing_page_tree',
            'sw_landing_page_tree_inner',
            'sw_landing_page_tree_items',
            'sw_landing_page_tree_items_actions',
            'sw_landing_page_tree_items_actions_edit',
            'sw_landing_page_tree_items_actions_duplicate',
            'sw_landing_page_tree_items_actions_delete',
            'sw_landing_page_tree_load_more',
            'sw_landing_page_tree_action',
        ],
        computed: [
            'landingPagesToDelete',
            'cmsLandingPageCriteria',
            'landingPage',
            'landingPageRepository',
            'landingPages',
            'hasMoreLandingPages',
            'disableContextMenu',
            'contextMenuTooltipText',
        ],
        methods: [
            'createdComponent',
            'loadLandingPages',
            'loadMoreLandingPages',
            'resetLandingPages',
            'reloadLandingPages',
            'checkedElementsCount',
            'deleteCheckedItems',
            'onDeleteLandingPage',
            'changeLandingPage',
            'duplicateElement',
            'createNewElement',
            'syncLandingPages',
            'createNewLandingPage',
            'addLandingPage',
            'addLandingPages',
            'removeFromStore',
            'getLandingPageUrl',
            'newLandingPageUrl',
        ],
    },
});
