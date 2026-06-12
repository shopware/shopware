import type { ContentElementNode } from '../../types/content-element.types';
import { castContentElementNodes } from '../../util/content-element-label.util';

import template from './sw-experience-studio-sidebar-tree.html.twig';
import './sw-experience-studio-sidebar-tree.scss';

interface AddElementPayload {
    parentElementId: string | null;
    slotName: string | null;
}

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
    ],

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: [
        'select-element',
        'add-element',
    ],

    computed: {
        layoutElements(): ContentElementNode[] {
            const layout = this.layout as Entity<'content_layout'> | null;

            return castContentElementNodes(layout?.layout);
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

        onAddRootElement(): void {
            this.$emit('add-element', {
                parentElementId: null,
                slotName: null,
            });
        },
    },
});
