import template from './sw-version.html.twig';
import './sw-version.scss';

/**
 * @private
 * @description Shows the header in the administration main menu
 * @status ready
 * @example-type static
 * @component-example
 * <div style="background: linear-gradient(to bottom, #303A4F, #2A3345); padding: 30px;">
 *     <sw-version class="collapsible-text"></sw-version>
 * </div>
 */
export default {
    name: 'sw-version',
    template
};
