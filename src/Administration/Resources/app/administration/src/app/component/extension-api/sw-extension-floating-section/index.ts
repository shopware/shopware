/**
 * @sw-package framework
 */
import template from './sw-extension-floating-section.html.twig';
import './sw-extension-floating-section.scss';

// Internal position, not a documented extension point: it may change or go away without notice.
// The template names the same position.
const POSITION_ID = 'sw-desktop__floating';

/**
 * @private
 * @description Floats the component sections of one position over the bottom left corner of its parent.
 * @status prototype
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data(): { isEmpty: boolean; resizeObserver: ResizeObserver | null } {
        return {
            // Inert until the content is measured
            isEmpty: true,
            resizeObserver: null,
        };
    },

    computed: {
        hasSections(): boolean {
            return (Shopware.Store.get('extensionComponentSections').identifier[POSITION_ID]?.length ?? 0) > 0;
        },
    },

    mounted() {
        // Extensions set their own height, so an empty panel is one whose content is 0 tall. It leaves the tab
        // order and the accessibility tree, but keeps its layout, so the extensions stay loaded and can grow again.
        this.resizeObserver = new ResizeObserver(([entry]) => {
            this.isEmpty = entry.contentRect.height === 0;
        });
        this.resizeObserver.observe(this.$refs.content as HTMLElement);
    },

    beforeUnmount() {
        this.resizeObserver?.disconnect();
    },
});
