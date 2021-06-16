import template from './sw-extension-card-base.html.twig';
import './sw-extension-card-base.scss';

const { Component, Utils, Filter } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-card-base', {
    template,

    inheritAttrs: false,

    inject: ['shopwareExtensionService', 'extensionStoreActionService', 'cacheApiService'],

    mixins: ['sw-extension-error'],

    props: {
        extension: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            showUninstallModal: false,
            showRemovalModal: false,
            showPermissionsModal: false,
            permissionsAccepted: false,
            showPrivacyModal: false,
            permissionModalActionLabel: null,
            openLink: null,
            extensionCanBeOpened: false,
        };
    },

    computed: {
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        defaultThemeAsset() {
            return this.assetFilter('administration/static/img/theme/default_theme_preview.jpg');
        },

        extensionCardClasses() {
            return {
                'is--deactivated': this.isInstalled && !this.extension.active,
            };
        },

        licensedExtension() {
            return this.extension.storeLicense;
        },

        description() {
            if (this.extension.shortDescription) {
                return this.extension.shortDescription;
            }

            return this.extension.description;
        },

        image() {
            if (this.extension.icon) {
                return this.extension.icon;
            }

            if (this.extension.iconRaw) {
                return `data:image/png;base64, ${this.extension.iconRaw}`;
            }

            return this.defaultThemeAsset;
        },

        isActive: {
            get() {
                if (!this.isInstalled) {
                    return false;
                }

                return this.extension.active;
            },
            set(active) {
                if (!this.isInstalled) {
                    return;
                }

                this.extension.active = active;

                this.$nextTick(() => {
                    this.changeExtensionStatus();
                }, 0);
            },
        },

        isInstalled() {
            return this.extension.installedAt !== null;
        },

        /* @deprecated tag:v6.5.0 - use data "extensionCanBeOpened" */
        canBeOpened() {
            return this.extensionCanBeOpened;
        },

        /* @deprecated tag:v6.5.0 - use data "openLink" */
        openLinkInformation() {
            return this.openLink;
        },

        privacyPolicyLink() {
            return this.extension.privacyPolicyLink;
        },

        permissions() {
            return Object.keys(this.extension.permissions).length ?
                this.extension.permissions : null;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        isRemovable() {
            if (this.extension.installedAt === null && this.extension.source === 'local') {
                return true;
            }

            return false;
        },

        isUninstallable() {
            if (this.extension.installedAt !== null) {
                return true;
            }

            return false;
        },

        isUpdateable() {
            if (!this.extension || this.extension.latestVersion === null) {
                return false;
            }

            return this.extension.latestVersion !== this.extension.version;
        },

        openLinkExists() {
            return !!this.openLink;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.openLink = await this.shopwareExtensionService.getOpenLink(this.extension);
            this.extensionCanBeOpened = await this.shopwareExtensionService.canBeOpened(this.extension);
        },

        emitUpdateList() {
            this.$emit('updateList');
        },

        getHelp() {
            // implemented in SAAS-1137
        },

        openPrivacyAndSafety() {
            window.open(this.extension.privacyPolicyLink, '_blank');
        },

        openRemovalModal() {
            this.showRemovalModal = true;
        },

        openUninstallModal() {
            this.showUninstallModal = true;
        },

        closeRemovalModal() {
            this.showRemovalModal = false;
        },

        closeUninstallModal() {
            this.showUninstallModal = false;
        },

        async closeModalAndUninstallExtension(removeData) {
            this.showUninstallModal = false;
            this.isLoading = true;

            try {
                await this.shopwareExtensionService.uninstallExtension(
                    this.extension.name,
                    this.extension.type,
                    removeData,
                );
                this.clearCacheAndReloadPage();
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async updateExtension() {
            this.isLoading = true;

            try {
                if (this.extension.updateSource === 'store') {
                    await this.extensionStoreActionService.downloadExtension(this.extension.name);
                }

                if (this.extension.installedAt) {
                    await this.shopwareExtensionService.updateExtension(
                        this.extension.name,
                        this.extension.type,
                    );
                }
                this.clearCacheAndReloadPage();
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async closeModalAndRemoveExtension() {
            // we close the modal in the called methods before updating the listing
            if (this.extension.storeLicense === null || this.extension.storeLicense.variant !== 'rent') {
                await this.removeExtension();
                this.showRemovalModal = false;

                return;
            }

            await this.cancelAndRemoveExtension();
            this.showRemovalModal = false;
        },

        async openExtension() {
            const openLink = await this.shopwareExtensionService.getOpenLink(this.extension);

            if (openLink) {
                this.$router.push(openLink);
            }
        },

        openPermissionsModalForInstall() {
            if (!this.permissions) {
                this.permissionsAccepted = true;
                this.installExtension();

                return;
            }

            this.permissionModalActionLabel = this.$tc(
                'sw-extension-store.component.sw-extension-card-base.labelAcceptAndInstall',
            );
            this.showPermissionsModal = true;
        },

        openPermissionsModal() {
            this.permissionModalActionLabel = null;
            this.showPermissionsModal = true;
        },

        closePermissionsModal() {
            this.permissionModalActionLabel = null;
            this.showPermissionsModal = false;
        },

        async closePermissionsModalAndInstallExtension() {
            this.permissionsAccepted = true;
            this.closePermissionsModal();
            await this.installExtension();
        },

        /*
         * Interface for deriving components
         */
        async changeExtensionStatus() {
            Utils.debug.warn(this._name, 'No implementation of changeExtensionStatus found');
        },

        installExtension() {
            Utils.debug.warn(this._name, 'No implementation of installExtension found');
        },

        async removeExtension() {
            try {
                this.showRemovalModal = false;
                this.isLoading = true;

                await this.shopwareExtensionService.removeExtension(
                    this.extension.name,
                    this.extension.type,
                );
                this.extension.active = false;
            } catch (e) {
                this.showStoreError(e);
            } finally {
                this.isLoading = false;
            }
        },

        cancelAndRemoveExtension() {
            Utils.debug.warn(this._name, 'No implementation of cancelAndRemoveExtension found');
        },

        openPrivacyModal() {
            this.showPrivacyModal = true;
        },

        closePrivacyModal() {
            this.showPrivacyModal = false;
        },

        clearCacheAndReloadPage() {
            return this.cacheApiService.clear()
                .then(() => {
                    window.location.reload();
                });
        },
    },
});
