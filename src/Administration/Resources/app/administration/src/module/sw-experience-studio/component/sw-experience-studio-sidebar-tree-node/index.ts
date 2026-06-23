import type { ContentElementNode } from '../../types/content-element.types';
import { getContentElementLabel } from '../../util/content-element-label.util';

import template from './sw-experience-studio-sidebar-tree-node.html.twig';
import './sw-experience-studio-sidebar-tree-node.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    name: 'sw-experience-studio-sidebar-tree-node',

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
    },

    emits: [
        'select-element',
        'add-element',
        'duplicate-element',
        'delete-element',
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

        label(): string {
            return getContentElementLabel(this.contentElement);
        },

        slotEntries(): Array<{ name: string; elements: ContentElementNode[] }> {
            const slots = this.contentElement.slots ?? {};

            return Object.entries(slots).map(([name, elements]) => ({
                name,
                elements: Array.isArray(elements) ? elements : [],
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

        onConfigureElement(): void {
            this.$emit('select-element', this.contentElement.id);
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
    },
});
