/**
 * @package system-settings
 */
import template from './sw-bulk-edit-form-field-renderer.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        suffixLabel() {
            return this.config?.suffixLabel ? this.config.suffixLabel : null;
        },
    },
};
