import template from './sw-extension-deactivation-modal.html.twig';
import './sw-extension-deactivation-modal.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-deactivation-modal', {
    template,

    props: {
        extensionName: {
            type: String,
            required: true,
        },
        isLicensed: {
            type: Boolean,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        removeHint() {
            return this.$tc('sw-extension-store.component.sw-extension-deactivation-modal.descriptionCancel', 0,
                {
                    removeLabel: this.isLicensed ?
                        this.$tc('sw-extension-store.component.sw-extension-card-base.contextMenu.cancelAndRemoveLabel') :
                        this.$tc('sw-extension-store.component.sw-extension-card-base.contextMenu.removeLabel'),
                });
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
});
