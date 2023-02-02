import template from './sw-extension-uninstall-modal.html.twig';
import './sw-extension-uninstall-modal.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-uninstall-modal', {
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

    data() {
        return {
            removePluginData: false,
        };
    },

    computed: {
        title() {
            return this.$t(
                'sw-extension-store.component.sw-extension-uninstall-modal.title',
                { extensionName: this.extensionName },
            );
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitUninstall() {
            this.$emit('uninstall-extension', this.removePluginData);
        },
    },
});
