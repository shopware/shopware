import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-ui-shell-update-2026-modal',
    load: () => import('./src/sw-ui-shell-update-2026-modal'),
    public: {
        computed: [
            'pages',
            'activePage',
            'isFirstPage',
            'isLastPage',
            'isSplitPinned',
            'isDragging',
            'userTheme',
            'compareStyle',
        ],
        methods: [
            'createdComponent',
            'beforeUnmountComponent',
            'resolveVisibility',
            'isIntendedAudience',
            'isExistingShop',
            'isExistingUser',
            'isBeforeRelease',
            'hasSeenModal',
            'markModalSeen',
            'onModalChange',
            'recordSeen',
            'onPreviousPage',
            'onNextPage',
            'onFinish',
            'onThemeChange',
            'onDragStart',
            'onDragMove',
            'onDragEnd',
            'clearHintHandoff',
            'currentSplitOf',
            'dragPositionOf',
            'imageSrc',
        ],
    },
});
