import template from './sw-inheritance-warning.html.twig';
import './sw-inheritance-warning.scss';

/**
 * @sw-package framework
 *
 * @private
 * @description
 * Renders inheritance warning
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-inheritance-warning :name="'This product'"></sw-inheritance-warning>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        name: {
            type: String,
            required: true,
        },
    },
};
