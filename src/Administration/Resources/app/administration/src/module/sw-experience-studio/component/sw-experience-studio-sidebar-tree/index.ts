import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutEntity } from '../../util/content-layout-repository.util';

import template from './sw-experience-studio-sidebar-tree.html.twig';
import './sw-experience-studio-sidebar-tree.scss';

interface AddElementPayload {
    parentElementId: string | null;
    slotName: string | null;
    anchorTop: number;
    anchorLeft: number;
}

interface MoveElementPayload {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
}

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    constants: {
        DRAG_GROUP: 'experience-studio-sidebar-tree',
    },

    inject: [
        'acl',
    ],

    props: {
        layout: {
            type: Object as PropType<ContentLayoutEntity | null>,
            required: false,
            default: null,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
        validateMoveTarget: {
            type: Function as unknown as PropType<((payload: MoveElementPayload) => boolean) | null>,
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

    computed: {
        layoutElements(): ContentElementNode[] {
            return this.layout?.layout ?? [];
        },

        hasElements(): boolean {
            return this.layoutElements.length > 0;
        },

        allowEdit(): boolean {
            return this.acl.can('experience_studio.editor');
        },
    },

    methods: {
        onSelectElement(elementId: string): void {
            this.$emit('select-element', elementId);
        },

        onAddElement(payload: AddElementPayload): void {
            this.$emit('add-element', payload);
        },

        onAddRootElement(event: MouseEvent): void {
            const trigger = event.currentTarget as HTMLElement | null;
            const bounds = trigger?.getBoundingClientRect();

            this.$emit('add-element', {
                parentElementId: null,
                slotName: null,
                anchorTop: bounds?.top ?? 0,
                anchorLeft: bounds ? bounds.right : 0,
            });
        },

        onDuplicateElement(elementId: string): void {
            this.$emit('duplicate-element', elementId);
        },

        onDeleteElement(elementId: string): void {
            this.$emit('delete-element', elementId);
        },

        onMoveElement(payload: MoveElementPayload): void {
            this.$emit('move-element', payload);
        },

        validateMoveDrop(
            dragData: { elementId: string } | null,
            dropData: Omit<MoveElementPayload, 'elementId'> | null,
        ): boolean {
            if (!this.allowEdit || !dragData || !dropData) {
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

        rootDropConfig() {
            return {
                dragGroup: (this.$options.constants as { DRAG_GROUP: string }).DRAG_GROUP,
                data: {
                    newParentElementId: null,
                    newSlotName: null,
                    newIndex: null,
                },
                // eslint-disable-next-line @typescript-eslint/unbound-method
                validateDrop: this.validateMoveDrop,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.onRootDrop,
            };
        },

        onRootDrop(dragData: { elementId: string } | null, dropData: Omit<MoveElementPayload, 'elementId'> | null): void {
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
