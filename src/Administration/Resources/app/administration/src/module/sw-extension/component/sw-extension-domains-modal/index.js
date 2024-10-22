import template from './sw-extension-domains-modal.html.twig';
import './sw-extension-domains-modal.scss';

/**
 * @package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['modal-close'],

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
            return this.$t('sw-extension-store.component.sw-extension-domains-modal.modalTitle', {
                extensionLabel: this.extensionLabel,
            });
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },
    },
};
