import template from './sw-extension-card-bought.html.twig';
import './sw-extension-card-bought.scss';

const { currency } = Shopware.Utils.format;

const { Component } = Shopware;

/**
 * @private
 */
Component.extend('sw-extension-card-bought', 'sw-extension-card-base', {
    template,

    mixins: ['sw-extension-error'],

    props: {
        license: {
            type: Object,
            required: true
        },

        extension: {
            type: Object,
            required: false,
            default: null
        },

        isLocalAvailable: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            showDeactivationModal: false,
            showRatingModal: false
        };
    },

    computed: {
        extensionCardClasses() {
            return {
                'sw-extension-card-bought': true,
                ...this.$super('extensionCardClasses')
            };
        },

        calculatedPrice() {
            return this.license.variant !== this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT ?
                null : currency(Number(this.license.netPrice), 'EUR');
        },

        detailLink() {
            return {
                name: 'sw.extension.store.detail',
                params: {
                    id: String(this.licensedExtension.id)
                }
            };
        },

        permissions() {
            return Object.keys(this.licensedExtension.permissions).length ?
                this.licensedExtension.permissions : null;
        }
    },

    methods: {
        async changeExtensionStatus() {
            // State in checkbox has already changed so we have to treat extension.active as the state to change to
            if (this.isActive) {
                await this.activateExtension();
                return;
            }

            if (!this.license
                || this.license.variant !== this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT) {
                await this.deactivateExtension();
                return;
            }

            this.showDeactivationModal = true;

            // reset state in next tick to not visually reset state until async operations finish
            this.$nextTick(() => {
                this.extension.active = !this.extension.active;
            });
        },

        async activateExtension() {
            try {
                this.isLoading = true;

                await this.shopwareExtensionService.activateExtension(
                    this.license.licensedExtension.name,
                    this.license.licensedExtension.type
                );
                this.extension.active = true;
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async deactivateExtension() {
            try {
                this.isLoading = true;

                await this.shopwareExtensionService.deactivateExtension(
                    this.license.licensedExtension.name,
                    this.license.licensedExtension.type
                );
                this.extension.active = false;
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        closeDeactivationModal() {
            this.showDeactivationModal = false;
        },

        async closeModalAndDeactivateExtension() {
            await this.deactivateExtension();
            this.showDeactivationModal = false;
        },

        async installExtension() {
            this.isLoading = true;

            try {
                if (!this.isLocalAvailable) {
                    await this.extensionStoreActionService.downloadExtension(
                        this.license.licensedExtension.name
                    );
                }


                await this.shopwareExtensionService.installExtension(
                    this.license.licensedExtension.name,
                    this.license.licensedExtension.type
                );
            } catch (e) {
                console.log(e);
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async cancelAndRemoveExtension() {
            try {
                this.isLoading = true;

                await this.shopwareExtensionService.cancelAndRemoveExtension(this.license.id);
                this.closeRemovalModal();

                this.$nextTick(() => {
                    this.emitUpdateList();
                });
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        openRatingModal() {
            this.showRatingModal = true;
        },

        closeRatingModal() {
            this.showRatingModal = false;
        }
    }
});
