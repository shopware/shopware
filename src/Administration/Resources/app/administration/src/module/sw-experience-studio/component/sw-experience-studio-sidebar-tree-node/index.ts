import type { ContentElementNode } from '../../types/content-element.types';
import { getContentElementLabel } from '../../util/content-element-label.util';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';

import template from './sw-experience-studio-sidebar-tree-node.html.twig';
import './sw-experience-studio-sidebar-tree-node.scss';

type MoveElementPayload = {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    name: 'sw-experience-studio-sidebar-tree-node',

    constants: {
        DRAG_GROUP: 'experience-studio-sidebar-tree',
    },

    inject: [
        'acl',
    ],

    props: {
        element: {
            type: Object,
            required: true,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
        depth: {
            type: Number,
            required: false,
            default: 0,
        },
        allowDragAndDrop: {
            type: Boolean,
            required: false,
            default: false,
        },
        validateMoveTarget: {
            type: Function,
            required: false,
            default: null,
        },
        parentElementId: {
            type: String,
            required: false,
            default: null,
        },
        parentSlotName: {
            type: String,
            required: false,
            default: null,
        },
        indexInParent: {
            type: Number,
            required: false,
            default: null,
        },
    },

    emits: [
        'select-element',
        'add-element',
        'duplicate-element',
        'delete-element',
        'move-element',
    ],

    data(): {
        isExpanded: boolean;
    } {
        return {
            isExpanded: true,
        };
    },

    computed: {
        contentElement(): ContentElementNode {
            return this.element as ContentElementNode;
        },

        elementTypeStore() {
            return Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
        },

        label(): string {
            return getContentElementLabel(this.contentElement);
        },

        typeIcon(): string {
            const configuredIcon = this.elementTypeStore.getByName(this.contentElement.component)?.icon;

            return configuredIcon && configuredIcon.length > 0 ? configuredIcon : 'bars-square-s';
        },

        slotEntries(): Array<{ name: string; elements: ContentElementNode[] }> {
            const slots = this.contentElement.slots ?? {};
            const definedSlots = this.elementTypeStore.getByName(this.contentElement.component)?.slots ?? [];
            const slotNames = Array.from(new Set([
                ...definedSlots.map((slot) => slot.name),
                ...Object.keys(slots),
            ]));

            return slotNames.map((name) => ({
                name,
                elements: Array.isArray(slots[name]) ? slots[name] : [],
            }));
        },

        hasSlots(): boolean {
            return this.slotEntries.length > 0;
        },

        isSelected(): boolean {
            return this.selectedElementId === this.contentElement.id;
        },

        allowEdit(): boolean {
            return this.acl.can('experience_studio.editor');
        },
    },

    methods: {
        onSelectElement(): void {
            this.$emit('select-element', this.contentElement.id);
        },

        onToggleExpand(): void {
            if (!this.hasSlots) {
                return;
            }

            this.isExpanded = !this.isExpanded;
        },

        onAddElement(slotName: string, event: MouseEvent): void {
            const trigger = event.currentTarget as HTMLElement | null;
            const bounds = trigger?.getBoundingClientRect();

            this.$emit('add-element', {
                parentElementId: this.contentElement.id,
                slotName,
                anchorTop: bounds?.top ?? 0,
                anchorLeft: bounds ? bounds.right : 0,
            });
        },

        onDuplicateElement(): void {
            this.$emit('duplicate-element', this.contentElement.id);
        },

        onDeleteElement(): void {
            this.$emit('delete-element', this.contentElement.id);
        },

        collectSubtreeIds(element: ContentElementNode): string[] {
            const nestedSlotElements = Object.values(element.slots ?? {}).flatMap((slotElements) => slotElements);
            const nestedIds = nestedSlotElements.flatMap((childElement) => this.collectSubtreeIds(childElement));

            return [element.id, ...nestedIds];
        },

        dragConfig() {
            return {
                dragGroup: this.$options.constants.DRAG_GROUP,
                disabled: !this.allowDragAndDrop,
                data: {
                    elementId: this.contentElement.id,
                    elementComponent: this.contentElement.component,
                    subtreeIds: this.collectSubtreeIds(this.contentElement),
                },
                onDrop: this.onDropElement,
            };
        },

        dropConfigForSlot(slotName: string) {
            return {
                dragGroup: this.$options.constants.DRAG_GROUP,
                data: {
                    newParentElementId: this.contentElement.id,
                    newSlotName: slotName,
                    newIndex: null,
                },
                validateDrop: this.validateMoveDrop,
            };
        },

        dropConfigForElement() {
            return {
                dragGroup: this.$options.constants.DRAG_GROUP,
                data: {
                    newParentElementId: this.parentElementId,
                    newSlotName: this.parentSlotName,
                    newIndex: this.indexInParent,
                },
                validateDrop: this.validateMoveDrop,
            };
        },

        validateMoveDrop(
            dragData: { elementId: string; subtreeIds: string[] } | null,
            dropData: Omit<MoveElementPayload, 'elementId'> | null,
        ): boolean {
            if (!this.allowDragAndDrop || !dragData || !dropData) {
                return false;
            }

            if (
                dropData.newParentElementId
                && dragData.subtreeIds.includes(dropData.newParentElementId)
            ) {
                return false;
            }

            if (typeof this.validateMoveTarget === 'function') {
                return this.validateMoveTarget({
                    elementId: dragData.elementId,
                    newParentElementId: dropData.newParentElementId,
                    newSlotName: dropData.newSlotName,
                    newIndex: dropData.newIndex,
                });
            }

            return true;
        },

        onDropElement(
            dragData: { elementId: string } | null,
            dropData: Omit<MoveElementPayload, 'elementId'> | null,
        ): void {
            if (!dragData || !dropData) {
                return;
            }

            this.$emit('move-element', {
                elementId: dragData.elementId,
                newParentElementId: dropData.newParentElementId,
                newSlotName: dropData.newSlotName,
                newIndex: dropData.newIndex,
            });
        },
    },
});
