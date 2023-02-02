import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import template from './sw-sortable-list.html.twig';
import './sw-sortable-list.scss';

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

/**
 * @public
 * @status ready
 * @example-type static
 * @description A configurable list component that can be used to sort items via drag-and-drop.
 * @component-example
 * <sw-sortable-list
 *      :items="[{id: '1', name: 'test'}, {id: '2', name: 'test2'}]"
 *      @itemsSorted="onSort">
 *          <template #item="{ item }">
 *              <div class="my-item">{{ item.name }}</div>
 *          </template>
 * </sw-sortable-list>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-sortable-list', {
    template,

    props: {
        items: {
            type: Array as PropType<Array<Entity>>,
            required: true,
        },
        sortable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default(): boolean {
                return true;
            },
        },
        dragConf: {
            type: Object as PropType<DragConfig>,
            required: false,
            default(): DragConfig {
                return this.defaultConfig;
            },
        },
    },

    data(): {
        defaultConfig: DragConfig,
        sortedItems: Array<Entity>,
        } {
        return {
            defaultConfig: {
                delay: 300,
                dragGroup: 'sw-sortable-list',
                validDragCls: 'is--valid-drag',
                preventEvent: true,
                disabled: false,
            } as DragConfig,
            sortedItems: [...this.items],
        };
    },

    computed: {
        hasItems(): boolean {
            return this.items.length > 0;
        },

        isSortable(): boolean {
            return this.sortable;
        },

        mergedDragConfig(): DragConfig {
            // eslint-disable-next-line @typescript-eslint/unbound-method
            this.defaultConfig.onDragEnter = this.onDragEnter;
            // eslint-disable-next-line @typescript-eslint/unbound-method
            this.defaultConfig.onDrop = this.onDrop;

            return { ...this.defaultConfig, ...this.dragConf } as DragConfig;
        },
    },

    methods: {
        hasOrderChanged(): boolean {
            return JSON.stringify(this.sortedItems) === JSON.stringify(this.items);
        },

        onDragEnter(draggedComponent: Entity, droppedComponent: Entity): void {
            if (!this.isSortable) {
                return;
            }

            if (!draggedComponent || !droppedComponent) {
                return;
            }

            if (draggedComponent.id === droppedComponent?.id) {
                return;
            }

            const draggedIndex = this.sortedItems.findIndex(c => c.id === draggedComponent.id);
            const droppedIndex = this.sortedItems.findIndex(c => c.id === droppedComponent.id);

            if (draggedIndex < 0 || droppedIndex < 0) {
                return;
            }

            this.sortedItems.splice(droppedIndex, 0, this.sortedItems.splice(draggedIndex, 1)[0]);
        },

        onDrop(): void {
            if (!this.isSortable) {
                return;
            }

            this.$emit('itemsSorted', this.sortedItems, this.hasOrderChanged());
        },
    },
});
