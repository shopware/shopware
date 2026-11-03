import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-multi-snippet-drag-and-drop',
    load: () => import('./src/sw-multi-snippet-drag-and-drop'),
    public: {
        props: [
            'value',
            'totalLines',
            'linePosition',
            'isLoading',
            'disabled',
            'selectionDisablingMethod',
            'dragConfig',
            'dropConfig',
            'getLabelProperty',
            'externalDragPreview',
            'isSnippetDragging',
        ],
        emits: [
            'drag-start',
            'drag-enter',
            'update:value',
            'drop-end',
            'add-new-line',
            'position-move',
            'open-snippet-modal',
        ],
        slots: [
            'selected-option',
            'label-property',
            'input',
        ],
        blocks: [
            'sw_multi_snippet_drag_and_drop',
        ],
        computed: [
            'errorObject',
            'mergedDragConfig',
            'mergedDropConfig',
            'isMaxLines',
            'isMinLines',
            'activeDragPreview',
            'isSnippetDragActive',
            'hasDragPreview',
            'dragPreviewSnippet',
        ],
        methods: [
            'onDragStart',
            'onDragEnter',
            'onDrop',
            'shouldShowPlaceholderBefore',
            'shouldShowPlaceholderAfter',
            'shouldShowEmptyPlaceholder',
            'isDragPreviewSource',
            'isSelectionDisabled',
            'onClickDismiss',
            'addNewLineAt',
            'moveToNewPosition',
            'onDelete',
            'openModal',
        ],
    },
});
