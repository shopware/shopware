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
export default {
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
    },
};
