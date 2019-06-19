import template from './sw-inheritance-warning.html.twig';
import './sw-inheritance-warning.scss';

/**
 * @public
 * @description
 * Renders inheritance warning
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-inheritance-warning :name="'This product'"></sw-inheritance-warning>
 */
export default {
    name: 'sw-inheritance-warning',
    template,
    props: {
        name: {
            type: String,
            required: true
        }
    }
};
