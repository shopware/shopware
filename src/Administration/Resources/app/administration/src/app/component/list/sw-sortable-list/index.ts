import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
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

interface ScrollOnDragConf {
    speed: number,
    margin: number,
    accelerationMargin: number,
}

/**
 * @package admin
 *
 * @private
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

    compatConfig: Shopware.compatConfig,

    props: {
        items: {
            type: Array as PropType<Array<Entity<keyof EntitySchema.Entities>>>,
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
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return this.defaultConfig;
            },
        },
        scrollOnDrag: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default(): boolean {
                return false;
            },
        },
        scrollOnDragConf: {
            type: Object as PropType<ScrollOnDragConf>,
            required: false,
            default(): ScrollOnDragConf {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return this.defaultScrollOnDragConf;
            },
        },
    },

    data(): {
        dragElement: Element|null,
        defaultConfig: DragConfig,
        defaultScrollOnDragConf: ScrollOnDragConf,
        sortedItems: Array<Entity<keyof EntitySchema.Entities>>,
        scrollEventTicking: boolean,
        } {
        return {
            defaultConfig: {
                delay: 300,
                dragGroup: 'sw-sortable-list',
                validDragCls: 'is--valid-drag',
                preventEvent: true,
                disabled: false,
            } as DragConfig,
            defaultScrollOnDragConf: {
                speed: 50,
                margin: 100,
                accelerationMargin: 0,
            } as ScrollOnDragConf,
            sortedItems: [...this.items],
            dragElement: null,
            scrollEventTicking: false,
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
            this.defaultConfig.onDragStart = this.onDragStart;
            // eslint-disable-next-line @typescript-eslint/unbound-method
            this.defaultConfig.onDragEnter = this.onDragEnter;
            // eslint-disable-next-line @typescript-eslint/unbound-method
            this.defaultConfig.onDrop = this.onDrop;

            return { ...this.defaultConfig, ...this.dragConf } as DragConfig;
        },

        mergedScrollOnDragConfig(): ScrollOnDragConf {
            return { ...this.defaultScrollOnDragConf, ...this.scrollOnDragConf } as ScrollOnDragConf;
        },

        scrollableParent(): Element|null {
            return this.findScrollableParent(this.$el as Element|null);
        },
    },

    methods: {
        findScrollableParent(node: Element|null): Element|null {
            if (node === null) {
                return null;
            }

            if (node.scrollHeight > node.clientHeight) {
                return node;
            }

            return this.findScrollableParent(node.parentElement);
        },

        hasOrderChanged(): boolean {
            return JSON.stringify(this.sortedItems) === JSON.stringify(this.items);
        },

        onDragEnter(
            draggedComponent: Entity<keyof EntitySchema.Entities>,
            droppedComponent: Entity<keyof EntitySchema.Entities>,
        ): void {
            if (!this.isSortable) {
                return;
            }

            if (!draggedComponent || !droppedComponent) {
                return;
            }

            if (draggedComponent.id === droppedComponent?.id) {
                return;
            }

            if (this.scrollOnDrag) {
                this.scroll();
            }

            const draggedIndex = this.sortedItems.findIndex(c => c.id === draggedComponent.id);
            const droppedIndex = this.sortedItems.findIndex(c => c.id === droppedComponent.id);

            if (draggedIndex < 0 || droppedIndex < 0) {
                return;
            }

            this.sortedItems.splice(droppedIndex, 0, this.sortedItems.splice(draggedIndex, 1)[0]);
        },

        onDragStart(dragConfig: DragConfig, draggedElement: Element, dragElement: Element): void {
            this.dragElement = dragElement;

            if (this.scrollOnDrag && this.scrollableParent !== null) {
                // eslint-disable-next-line @typescript-eslint/unbound-method
                this.scrollableParent.addEventListener('scroll', this.onScroll);
            }
        },

        onScroll(): void {
            if (!this.scrollEventTicking) {
                window.requestAnimationFrame(() => {
                    this.scroll();
                    this.scrollEventTicking = false;
                });

                this.scrollEventTicking = true;
            }
        },

        scroll(): void {
            if (!this.scrollableParent || !this.dragElement) {
                return;
            }

            const scrollableRect = this.scrollableParent.getBoundingClientRect();
            const dragRect = this.dragElement.getBoundingClientRect();
            const topDistance = dragRect.top - scrollableRect.top;
            const bottomDistance = scrollableRect.bottom - dragRect.bottom;
            const scrollDistance = Math.round(
                (this.scrollableParent.scrollHeight - this.scrollableParent.clientHeight) / this.scrollableParent.scrollTop,
            );

            let speed = this.mergedScrollOnDragConfig.speed;

            if (topDistance < this.mergedScrollOnDragConfig.margin && scrollDistance !== 0) {
                if (topDistance < this.mergedScrollOnDragConfig.accelerationMargin) {
                    speed *= 1 + Math.abs(topDistance / 20);
                }

                this.scrollableParent.scrollBy({
                    top: -speed,
                    left: 0,
                    behavior: 'smooth',
                });
            }

            if (bottomDistance < this.mergedScrollOnDragConfig.margin && scrollDistance !== 100) {
                if (bottomDistance < this.mergedScrollOnDragConfig.accelerationMargin) {
                    speed *= 1 + Math.abs(bottomDistance / 20);
                }

                this.scrollableParent.scrollBy({
                    top: speed,
                    left: 0,
                    behavior: 'smooth',
                });
            }
        },

        onDrop(): void {
            this.dragElement = null;

            if (this.scrollOnDrag && this.scrollableParent !== null) {
                // eslint-disable-next-line @typescript-eslint/unbound-method
                this.scrollableParent.removeEventListener('scroll', this.onScroll);
            }

            if (!this.isSortable) {
                return;
            }

            this.$emit('items-sorted', this.sortedItems, this.hasOrderChanged());
        },
    },
});
