import template from './sw-media-folder-thumbnail.html.twig';
import './sw-media-folder-thumbnail.scss';

/**
 * @sw-package discovery
 *
 * The folder artwork as an inline svg, so the fill and stroke follow the
 * meteor color tokens and react to light/dark theme switches — an svg loaded
 * via an img tag is an isolated document that page CSS variables cannot reach.
 */
/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default',
            validator(value: string): boolean {
                return [
                    'default',
                    'back',
                    'back-breadcrumb',
                ].includes(value);
            },
        },

        // Strokes the default variant in this color with a tinted fill, back variants ignore it
        color: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        isColored(): boolean {
            return !!this.color;
        },

        colorStyle(): Record<string, string> | null {
            return this.isColored ? { '--sw-media-folder-thumbnail-color': this.color } : null;
        },
    },
});
