import type { PropType } from 'vue';
import type { Snippet } from 'src/core/service/api/custom-snippet.api.service';
import template from './sw-multi-snippet-drag-and-drop.html.twig';
import './sw-multi-snippet-drag-and-drop.scss';

const { Component } = Shopware;

interface DragConfig {
    delay: number,
    dragGroup: number | string,
    draggableCls: string,
    draggingStateCls: string,
    dragElementCls: string,
    validDragCls: string,
    invalidDragCls: string,
    preventEvent: boolean,
    validateDrop: boolean,
    validateDrag: boolean,
    onDragStart: (...args: never[]) => void,
    onDragEnter: (...args: never[]) => void,
    onDragLeave: (...args: never[]) => void,
    onDrop: (...args: never[]) => void,
    data: Record<string, unknown>,
    disabled: boolean,
}

interface DragItem {
    index: number,
    linePosition?: number | null,
    snippet: Snippet[]
}

const DEFAULT_MIN_LINES = 1 as number;
const DEFAULT_MAX_LINES = 10 as number;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-multi-snippet-drag-and-drop', {
    template,

    model: {
        prop: 'value',
        event: 'change',
    },

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

        labelProperty: {
            type: String,
            required: false,
            default: 'value',
        },

        valueProperty: {
            type: String,
            required: false,
            default: 'value',
        },

        selectionDisablingMethod: {
            type: Function,
            required: false,
            default: () => false,
        },

        dragConfig: {
            type: Object,
            required: false,
            default(): DragConfig {
                return this.defaultConfig;
            },
        },

        dropConfig: {
            type: Object,
            required: false,
            default(): DragConfig {
                return this.defaultConfig;
            },
        },

        getLabelProperty: {
            type: Function,
            required: false,
            default: (value: string) => value,
        },
    },

    data(): {
        defaultConfig: DragConfig,
        } {
        return {
            defaultConfig: {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                preventEvent: true,
                disabled: this.disabled,
            } as DragConfig,
        };
    },

    computed: {
        errorObject(): null {
            return null;
        },

        mergedDragConfig(): DragConfig {
            return {
                ...this.defaultConfig,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragStart: this.dragStart,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragEnter: this.onDragEnter,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.dragEnd,
                ...this.dragConfig,
            } as DragConfig;
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

        isMinLines() :boolean {
            return this.totalLines <= DEFAULT_MIN_LINES;
        },
    },

    methods: {
        dragStart(config: DragConfig, element: string, dragElement: string): void {
            this.$emit('drag-start', { config, element, dragElement });
        },

        onDragEnter(dragData: DragConfig, dropData: DragConfig) {
            if (!dragData || !dropData) {
                return;
            }

            this.$emit('drag-enter', { dragData, dropData });
        },

        dragEnd(dragData: DragItem, dropData: DragItem) {
            if (!dragData || !dropData) {
                return;
            }

            if (dragData.linePosition === dropData.linePosition) {
                const newValue = Object.assign(
                    [],
                    this.value,
                    {
                        [dragData.index]: this.value[dropData.index],
                        [dropData.index]: this.value[dragData.index],
                    },
                );

                this.$emit(
                    'change',
                    this.linePosition,
                    newValue,
                );

                return;
            }

            this.$emit('drag-end', this.linePosition, { dragData, dropData });
        },

        isSelectionDisabled(selection: $TSFixMe): boolean {
            if (this.disabled) {
                return true;
            }

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return this.selectionDisablingMethod(selection);
        },

        onClickDismiss(index: number) {
            this.$emit(
                'change',
                this.linePosition,
                this.value.filter((_, key) => key !== index),
            );
        },

        addNewLineAt(position: number) {
            this.$emit('add-new-line', this.linePosition, position);
        },

        moveToLocation(position = null) {
            this.$emit('location-move', this.linePosition, position);
        },

        onDelete() {
            this.$emit('change', this.linePosition);
        },

        openModal() {
            this.$emit('open-snippet-modal', this.linePosition);
        },
    },
});
