import template from './sw-extension-privacy-policy-extensions-modal.html.twig';
import './sw-extension-privacy-policy-extensions-modal.scss';

/**
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
                0,
                { extensionLabel: this.extensionName },
            );
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },
    },
};
