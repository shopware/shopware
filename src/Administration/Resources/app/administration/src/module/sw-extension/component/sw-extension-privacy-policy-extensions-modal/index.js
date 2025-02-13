import template from './sw-extension-privacy-policy-extensions-modal.html.twig';
import './sw-extension-privacy-policy-extensions-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: ['modal-close'],

    props: {
        extensionName: {
            type: String,
            required: true,
        },

        privacyPolicyExtension: {
            type: String,
            required: true,
        },
    },

    computed: {
        title() {
            return this.$tc(
                'sw-extension-store.component.sw-extension-privacy-policy-extensions-modal.title',
                {
                    extensionLabel: this.extensionName,
                },
                0,
            );
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },
    },
};
