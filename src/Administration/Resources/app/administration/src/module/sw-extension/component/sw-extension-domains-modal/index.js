import template from './sw-extension-domains-modal.html.twig';
import './sw-extension-domains-modal.scss';

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        extensionLabel: {
            type: String,
            required: true,
        },

        domains: {
            type: Array,
            required: true,
        },
    },

    computed: {
        modalTitle() {
            return this.$t(
                'sw-extension-store.component.sw-extension-domains-modal.modalTitle',
                { extensionLabel: this.extensionLabel },
            );
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },
    },
};
