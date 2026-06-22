import type { ComponentObjectPropsOptions } from 'vue';
import type { DragConfig } from 'src/app/directive/dragdrop.directive';
import template from './sw-multi-snippet-drag-and-drop.html.twig';
import './sw-multi-snippet-drag-and-drop.scss';

const { Component } = Shopware;

interface DragItem {
    index: number;
    linePosition?: number | null;
    snippet: string[];
    targetIndex?: number;
}

interface DragPreview {
    dragIndex: number;
    targetIndex: number;
}

const DEFAULT_MIN_LINES = 1 as number;
const DEFAULT_MAX_LINES = 10 as number;

/**
 * @sw-package fundamentals@discovery
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['feature'],

    props: {
        value: {
            type: Array as PropType<Array<string[]>>,
            required: true,
        },

        totalLines: {
            type: Number,
            required: true,
        },

        linePosition: {
            type: Number,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        selectionDisablingMethod: {
            type: Function,
            required: false,
            default: () => false,
        },

        dragConfig: {
            type: Object,
            required: false,
            default(props: ComponentObjectPropsOptions<{ disabled: boolean }>): DragConfig<DragItem> {
                return {
                    delay: 200,
                    dragGroup: 'sw-multi-snippet',
                    validDragCls: 'is--valid-drag',
                    preventEvent: true,
                    disabled: props.disabled,
                } as unknown as DragConfig<DragItem>;
            },
        },

        dropConfig: {
            type: Object,
            required: false,
            default(props: ComponentObjectPropsOptions<{ disabled: boolean }>): DragConfig<DragItem> {
                return {
                    delay: 200,
                    dragGroup: 'sw-multi-snippet',
                    validDragCls: 'is--valid-drag',
                    preventEvent: true,
                    disabled: props.disabled,
                } as unknown as DragConfig<DragItem>;
            },
        },

        getLabelProperty: {
            type: Function,
            required: false,
            default: (value: string) => value,
        },
    },

    data(): {
        defaultConfig: DragConfig<DragItem>;
        dragPreview: DragPreview | null;
        isDragging: boolean;
    } {
        return {
            defaultConfig: {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                preventEvent: true,
                disabled: this.disabled,
            } as DragConfig<DragItem>,
            dragPreview: null,
            isDragging: false,
        };
    },

    computed: {
        errorObject(): null {
            return null;
        },

        mergedDragConfig(): DragConfig<DragItem> {
            return {
                ...this.defaultConfig,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragStart: this.onDragStart,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragEnter: this.onDragEnter,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.onDrop,
                ...this.dragConfig,
            } as DragConfig<DragItem>;
        },

        mergedDropConfig(): DragConfig {
            return {
                ...this.defaultConfig,
                ...this.dropConfig,
            } as DragConfig;
        },

        isMaxLines(): boolean {
            return this.totalLines >= DEFAULT_MAX_LINES;
        },

        isMinLines(): boolean {
            return this.totalLines <= DEFAULT_MIN_LINES;
        },

        hasDragPreview(): boolean {
            return !!this.dragPreview;
        },

        dragPreviewSnippet(): string[] | null {
            if (!this.dragPreview) {
                return null;
            }

            return this.value[this.dragPreview.dragIndex] ?? null;
        },
    },

    methods: {
        onDragStart(config: DragConfig<DragItem>, element: HTMLElement, dragElement: HTMLElement): void {
            this.isDragging = true;

            this.$emit('drag-start', { config, element, dragElement });
        },

        onDragEnter(dragData: DragItem | null, dropData: DragItem | null) {
            if (!dragData || !dropData) {
                return;
            }

            if (dragData.linePosition === dropData.linePosition && typeof dropData.targetIndex === 'number') {
                this.dragPreview = {
                    dragIndex: dragData.index,
                    targetIndex: dropData.targetIndex,
                };
            } else if (dragData.linePosition !== dropData.linePosition) {
                this.dragPreview = null;
            }

            this.$emit('drag-enter', { dragData, dropData });
        },

        onDrop(dragData: DragItem | null, dropData: DragItem | null) {
            const dragPreview = this.dragPreview as DragPreview | null;

            this.dragPreview = null;
            this.isDragging = false;

            if (!dragData || (!dropData && !dragPreview)) {
                return;
            }

            if (!dropData || dragData.linePosition === dropData.linePosition) {
                const newValue = [...this.value];
                const [snippet] = newValue.splice(dragData.index, 1);
                const fallbackTargetIndex =
                    dropData && dragData.index < dropData.index ? dropData.index + 1 : (dropData?.index ?? dragData.index);
                const targetIndex = dropData?.targetIndex ?? dragPreview?.targetIndex ?? fallbackTargetIndex;
                const insertIndex = targetIndex > dragData.index ? targetIndex - 1 : targetIndex;

                newValue.splice(insertIndex, 0, snippet);

                this.$emit('update:value', this.linePosition, newValue);

                return;
            }

            this.$emit('drop-end', this.linePosition, { dragData, dropData });
        },

        shouldShowPlaceholderBefore(index: number): boolean {
            return !!this.dragPreview && this.dragPreview.targetIndex === index;
        },

        shouldShowPlaceholderAfter(index: number): boolean {
            return !!this.dragPreview && this.dragPreview.targetIndex === this.value.length && index === this.value.length - 1;
        },

        isDragPreviewSource(index: number): boolean {
            return !!this.dragPreview && this.dragPreview.dragIndex === index;
        },

        isSelectionDisabled(selection: $TSFixMe): boolean {
            if (this.disabled) {
                return true;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-call
            return this.selectionDisablingMethod(selection);
        },

        onClickDismiss(index: number) {
            this.$emit(
                'update:value',
                this.linePosition,
                this.value.filter((_, key) => key !== index),
            );
        },

        addNewLineAt(position: number) {
            this.$emit('add-new-line', this.linePosition, position);
        },

        moveToNewPosition(position = null) {
            this.$emit('position-move', this.linePosition, position);
        },

        onDelete() {
            this.$emit('update:value', this.linePosition);
        },

        openModal() {
            this.$emit('open-snippet-modal', this.linePosition);
        },
    },
});
