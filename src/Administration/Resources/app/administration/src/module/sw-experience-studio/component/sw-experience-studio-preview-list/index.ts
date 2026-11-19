import type { CanvasFrame, Viewport } from '../sw-experience-studio-canvas/canvas.config';
import { VIEWPORT_ICONS } from '../sw-experience-studio-canvas/canvas.config';
import template from './sw-experience-studio-preview-list.html.twig';
import './sw-experience-studio-preview-list.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        frames: {
            type: Array as PropType<CanvasFrame[]>,
            required: true,
        },
        layoutId: {
            type: String,
            required: true,
        },
    },

    emits: ['select', 'close'],

    computed: {
        viewportIcons(): Record<Viewport, string> {
            return VIEWPORT_ICONS;
        },

        layoutFrames(): CanvasFrame[] {
            return this.frames.filter((frame) => frame.layout.id === this.layoutId);
        },
    },
});
