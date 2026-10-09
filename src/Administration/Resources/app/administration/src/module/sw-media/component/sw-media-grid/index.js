import template from './sw-media-grid.html.twig';
import './sw-media-grid.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    props: {
        presentation: {
            required: false,
            type: String,
            default: 'medium-preview',
            validator(value) {
                return [
                    'small-preview',
                    'medium-preview',
                    'large-preview',
                    'list-preview',
                ].includes(value);
            },
        },
    },

    computed: {
        mediaColumnDefinitions() {
            return {
                'grid-template-columns': `repeat(auto-fit, ${this.gridColumnWidth}px)`,
            };
        },

        presentationClass() {
            return `sw-media-grid__presentation--${this.presentation}`;
        },
    },
};
