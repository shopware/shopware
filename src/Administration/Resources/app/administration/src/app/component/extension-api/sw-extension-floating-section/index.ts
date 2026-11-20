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

    data(): { anchorElement: HTMLElement | null; isEmpty: boolean; resizeObserver: ResizeObserver | null } {
        return {
            anchorElement: null,
            isEmpty: false,
            resizeObserver: null,
        };
    },

    computed: {
        hasSections(): boolean {
            return (Shopware.Store.get('extensionComponentSections').identifier[POSITION_ID]?.length ?? 0) > 0;
        },

        isOpened(): boolean {
            return this.hasSections && this.anchorElement !== null;
        },

        floatingUiOptions(): { placement: 'top-start' } {
            return { placement: 'top-start' };
        },

        // Meteor turns this off by default. Without it the panel only follows scrolling and window resizes,
        // and stays behind when the anchor moves for another reason, such as the menu collapsing.
        autoUpdateOptions(): { layoutShift: boolean } {
            return { layoutShift: true };
        },
    },

    watch: {
        isOpened: {
            handler(isOpened: boolean): void {
                if (isOpened) {
                    this.observeContent();
                    return;
                }

                this.resizeObserver?.disconnect();
            },
            flush: 'post',
        },

        isEmpty(): void {
            this.updatePanel();
        },
    },

    mounted() {
        this.anchorElement = this.$refs.anchor as HTMLElement;
    },

    beforeUnmount() {
        this.resizeObserver?.disconnect();
    },

    methods: {
        // Extensions set their own height, so an empty panel is one whose content is 0 tall
        observeContent(): void {
            const content = this.$refs.content as HTMLElement | undefined;
            if (!content) {
                return;
            }

            this.resizeObserver?.disconnect();
            this.resizeObserver = new ResizeObserver(([entry]) => {
                this.isEmpty = entry.contentRect.height === 0;
            });
            this.resizeObserver.observe(content);
            this.updatePanel();
        },

        // The floating UI only copies classes onto the teleported panel, so its other attributes are set here
        updatePanel(): void {
            const panel = (this.$refs.content as HTMLElement | undefined)?.closest('.mt-floating-ui__content');
            if (!panel) {
                return;
            }

            // The panel is focusable, so it needs a name, and a landmark lets screen reader users jump to it
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-label', this.$t('global.sw-extension-floating-section.label'));

            // An empty panel leaves the tab order and the accessibility tree, but keeps its layout,
            // so the extensions stay loaded and can grow again
            panel.toggleAttribute('inert', this.isEmpty);
        },
    },
});
