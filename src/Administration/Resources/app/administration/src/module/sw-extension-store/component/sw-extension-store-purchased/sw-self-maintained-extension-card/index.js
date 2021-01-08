import template from '../../sw-extension-permissions-details-modal/sw-extension-permissions-details-modal.html.twig';

const { Component } = Shopware;

Component.extend('sw-self-maintained-extension-card', 'sw-extension-card-base', {
    template,

    props: {
        extension: {
            type: Object,
            required: true
        },

        license: {
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

    computed: {
        extensionCardClasses() {
            return {
                'sw-self-maintained-extension-card': true,
                ...this.$super('extensionCardClasses')
            };
        },

        permissions() {
            return Object.keys(this.extension.permissions).length ?
                this.extension.permissions : null;
        },

        isInstalled() {
            return this.extension.installedAt !== null;
        }
    },

    methods: {
        async changeExtensionStatus() {
            // State in checkbox has already changed so we have to treat extension.active as the state to change to
            if (this.isActive) {
                await this.activateExtension();
                return;
            }

            await this.deactivateExtension();
        },

        async installExtension() {
            this.isLoading = true;

            try {
                await this.shopwareExtensionService.installExtension(
                    this.extension.name,
                    this.extension.type
                );
            } catch (e) {
                console.log(e);
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async activateExtension() {
            try {
                this.isLoading = true;

                await this.shopwareExtensionService.activateExtension(
                    this.extension.name,
                    this.extension.type
                );
                this.extension.active = true;

                window.location.reload();
            } catch (e) {
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async deactivateExtension() {
            try {
                this.isLoading = true;

                await this.shopwareExtensionService.deactivateExtension(
                    this.extension.name,
                    this.extension.type
                );
                this.extension.active = false;

                window.location.reload();
            } catch (e) {
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async removeExtension() {
            try {
                this.showRemovalModal = false;
                this.isLoading = true;

                await this.shopwareExtensionService.removeExtension(
                    this.extension.name,
                    this.extension.type
                );
                this.extension.active = false;
            } catch (e) {
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        }
    }
});

