import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutEntity } from '../../util/content-layout-repository.util';
import type { ScrollNavigationAnchor } from '../../util/scroll-navigation-settings.util';
import { readScrollNavigationSettings } from '../../util/scroll-navigation-settings.util';

import template from './sw-experience-studio-sidebar-tree.html.twig';
import './sw-experience-studio-sidebar-tree.scss';

interface AddElementPayload {
    parentElementId: string | null;
    slotName: string | null;
    anchorElement: HTMLElement | null;
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
        'open-page-settings',
    ],

    computed: {
        layoutElements(): ContentElementNode[] {
            return this.layout?.layout ?? [];
        },

        anchors(): Record<string, ScrollNavigationAnchor> {
            return readScrollNavigationSettings(this.layout).anchors;
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
            this.$emit('add-element', {
                parentElementId: null,
                slotName: null,
                anchorElement: event.currentTarget as HTMLElement | null,
            });
        },

        onOpenPageSettings(): void {
            this.$emit('open-page-settings');
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
