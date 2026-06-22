import type { ComponentObjectPropsOptions } from 'vue';
import type { DragConfig } from 'src/app/directive/dragdrop.directive';
import template from './sw-multi-snippet-drag-and-drop.html.twig';
import './sw-multi-snippet-drag-and-drop.scss';

const { Component } = Shopware;

interface DragItem {
    index: number;
    linePosition?: number | null;
    snippet: string[];
}

interface DragPreview {
    dragIndex: number;
    dropIndex: number;
    position: 'before' | 'after';
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
            this.$emit('drag-start', { config, element, dragElement });
        },

        onDragEnter(dragData: DragItem, dropData: DragItem) {
            if (!dragData || !dropData) {
                return;
            }

            if (dragData.linePosition === dropData.linePosition) {
                this.dragPreview = {
                    dragIndex: dragData.index,
                    dropIndex: dropData.index,
                    position: this.getDragPreviewPosition(dragData, dropData),
                };
            } else {
                this.dragPreview = null;
            }

            this.$emit('drag-enter', { dragData, dropData });
        },

        onDrop(dragData: DragItem, dropData: DragItem) {
            const dragPreview = this.dragPreview as DragPreview | null;

            this.dragPreview = null;

            if (!dragData || !dropData) {
                return;
            }

            if (dragData.linePosition === dropData.linePosition) {
                const newValue = [...this.value];
                const [snippet] = newValue.splice(dragData.index, 1);
                const position: DragPreview['position'] =
                    dragPreview?.position ?? (dragData.index < dropData.index ? 'after' : 'before');
                const targetIndex = dropData.index - (dragData.index < dropData.index ? 1 : 0);

                newValue.splice(position === 'after' ? targetIndex + 1 : targetIndex, 0, snippet);

                this.$emit('update:value', this.linePosition, newValue);

                return;
            }

            this.$emit('drop-end', this.linePosition, { dragData, dropData });
        },

        getDragPreviewPosition(dragData: DragItem, dropData: DragItem): DragPreview['position'] {
            if (dragData.index === dropData.index) {
                return 'before';
            }

            const previousPreview = this.dragPreview;
            const previousDropIndex = previousPreview?.dropIndex ?? dragData.index;

            if (previousPreview?.dropIndex === dropData.index) {
                return previousPreview.position;
            }

            const isMovingLeft = dropData.index < previousDropIndex;
            const isMovingRight = dropData.index > previousDropIndex;
            const isReturningAcrossHiddenSource =
                previousPreview &&
                !this.isOriginalDragPreview(dragData.index, previousPreview) &&
                ((isMovingLeft && dropData.index === dragData.index - 1) ||
                    (isMovingRight && dropData.index === dragData.index + 1));

            if (isReturningAcrossHiddenSource) {
                return dropData.index < dragData.index ? 'after' : 'before';
            }

            return isMovingLeft ? 'before' : 'after';
        },

        isOriginalDragPreview(dragIndex: number, dragPreview: DragPreview): boolean {
            return (
                (dragPreview.dropIndex === dragIndex && dragPreview.position === 'before') ||
                (dragPreview.dropIndex === dragIndex - 1 && dragPreview.position === 'after') ||
                (dragPreview.dropIndex === dragIndex + 1 && dragPreview.position === 'before')
            );
        },

        shouldShowPlaceholderBefore(index: number): boolean {
            return !!this.dragPreview && this.dragPreview.position === 'before' && this.dragPreview.dropIndex === index;
        },

        shouldShowPlaceholderAfter(index: number): boolean {
            return !!this.dragPreview && this.dragPreview.position === 'after' && this.dragPreview.dropIndex === index;
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
