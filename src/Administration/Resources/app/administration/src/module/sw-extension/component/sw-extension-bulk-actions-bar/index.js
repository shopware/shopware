import template from './sw-extension-bulk-actions-bar.html.twig';
import './sw-extension-bulk-actions-bar.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: [
        'select-all',
        'clear',
        'run-action',
    ],

    props: {
        selectedCount: {
            type: Number,
            required: true,
        },
        applicableCounts: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        onAction(action, applicableCount) {
            if (this.disabled || !applicableCount) {
                return;
            }

            this.$emit('run-action', action);
        },
    },
};
