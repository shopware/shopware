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
    name: 'sw-category-tree',
    load: () => import('./src/sw-category-tree'),
    public: {
        props: [
            'categoryId',
            'currentLanguageId',
            'allowEdit',
            'allowCreate',
            'allowDelete',
        ],
        emits: [
            'category-checked-elements-count',
            'unsaved-changes',
        ],
        blocks: [
            'sw_category_tree',
            'sw_category_tree_items',
        ],
        computed: [
            'categoriesToDelete',
            'categoryRepository',
            'category',
            'categories',
            'disableContextMenu',
            'contextMenuTooltipText',
            'criteria',
            'criteriaWithChildren',
            'cmsPageRepository',
            'productRepository',
        ],
        methods: [
            'createdComponent',
            'openInitialTree',
            'loadActiveCategory',
            'onUpdatePositions',
            'syncProducts',
            'indexProducts',
            'checkedElementsCount',
            'deleteCheckedItems',
            'onDeleteCategory',
            'fixSortingForCategories',
            'getNextCategory',
            'changeCategory',
            'onGetTreeItems',
            'getChildrenFromParent',
            'loadRootCategories',
            'createNewElement',
            'createNewCategory',
            'syncSiblings',
            'addCategory',
            'addCategories',
            'removeFromStore',
            'getDeletedIds',
            'getCategoryUrl',
            'isHighlighted',
            'isErrorNavigationEntryPoint',
            'entryPointWarningMessage',
        ],
    },
});
