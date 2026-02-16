import template from './sw-mail-template-validation-result.html.twig';
import './sw-mail-template-validation-result.scss';

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        title: {
            type: String,
            required: false,
            default: '',
        },
        hint: {
            type: String,
            required: false,
            default: '',
        },
        type: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        icon() {
            switch (this.type) {
                case 'error':
                    return 'solid-exclamation-circle';
                case 'warning':
                    return 'solid-exclamation-triangle';
                default:
                    return 'solid-exclamation-circle';
            }
        },

        iconColor() {
            switch (this.type) {
                case 'error':
                    return 'var(--color-icon-critical-default)';
                case 'warning':
                    return 'var(--color-icon-attention-default)';
                default:
                    return 'var(--color-icon-critical-default)';
            }
        },
    },
};
