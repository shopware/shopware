import template from './sw-extension-deactivation-modal.html.twig';
import './sw-extension-deactivation-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: ['modal-close', 'extension-deactivate'],

    props: {
        extensionName: {
            type: String,
            required: true,
        },
        isLicensed: {
            type: Boolean,
            required: true,
        },
        hasDeletableFiles: {
            type: Boolean,
            required: false,
            default: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        removeHint() {
            return this.$t(
                'sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancel',
                {
                    removeLabel: this.removeLabel,
                },
                0,
            );
        },

        removeLabel() {
            if (!this.isLicensed) {
                return this.$t('global.default.remove');
            }

            if (!this.hasDeletableFiles) {
                return this.$t('sw-extension-store.component.sw-extension-card-base.contextMenu.cancelSubscriptionLabel');
            }

            return this.$t('sw-extension-store.component.sw-extension-card-base.contextMenu.cancelAndRemoveLabel');
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitDeactivate() {
            this.$emit('extension-deactivate');
        },
    },
};
