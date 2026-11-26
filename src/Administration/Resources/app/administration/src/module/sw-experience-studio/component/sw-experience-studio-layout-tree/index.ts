import type { CanvasFrame } from '../sw-experience-studio-canvas/canvas.config';
import template from './sw-experience-studio-layout-tree.html.twig';
import './sw-experience-studio-layout-tree.scss';

/** @private @sw-package discovery */
export type LayoutTreeItem = {
    id: string;
    name: string;
    isDirty: boolean;
    saveError: string | null;
    canManageAssignments: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        items: {
            type: Array as PropType<LayoutTreeItem[]>,
            required: true,
        },
        frames: {
            type: Array as PropType<CanvasFrame[]>,
            required: true,
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    emits: [
        'select-layout',
        'select-frame',
        'close-layout',
        'close-frame',
        'assign',
    ],

    data() {
        return { collapsedLayoutIds: [] as string[] };
    },

    computed: {
        activeLayoutId(): string | null {
            return this.frames.find((frame) => frame.isActive)?.layout.id ?? null;
        },
    },

    methods: {
        isExpanded(layoutId: string): boolean {
            return !this.collapsedLayoutIds.includes(layoutId);
        },

        toggleLayout(layoutId: string): void {
            this.collapsedLayoutIds = this.isExpanded(layoutId)
                ? [...this.collapsedLayoutIds, layoutId]
                : this.collapsedLayoutIds.filter((id) => id !== layoutId);
        },
    },
});
