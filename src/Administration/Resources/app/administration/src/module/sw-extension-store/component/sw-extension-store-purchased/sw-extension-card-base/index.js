import template from './sw-extension-card-base.html.twig';
import './sw-extension-card-base.scss';

const { Component, Utils, Filter } = Shopware;

Component.register('sw-extension-card-base', {
    name: 'sw-extension-card-base',
    template,
    inheritAttrs: false,

    inject: ['shopwareExtensionService', 'extensionStoreActionService'],

    mixins: [],

    props: {
        license: {
            type: Object,
            required: false,
            default: null
        },

        extension: {
            type: Object,
            required: false,
            default: null
        },

        updateLocation: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            isLoading: false,
            showUninstallModal: false,
            showRemovalModal: false,
            showPermissionsModal: false,
            permissionsAccepted: false,
            showPrivacyModal: false,
            permissionModalActionLabel: null
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
                'is--deactivated': this.isInstalled && !this.extension.active
            };
        },

        licensedExtension() {
            return this.license ? this.license.licensedExtension : null;
        },

        description() {
            if (this.getPropValue('shortDescription')) {
                return this.getPropValue('shortDescription');
            }

            return this.getPropValue('description');
        },

        image() {
            if (this.getPropValue('icon')) {
                return this.getPropValue('icon');
            }

            if (this.getPropValue('iconRaw')) {
                return `data:image/png;base64, ${this.getPropValue('iconRaw')}`;
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
            }
        },

        isInstalled() {
            return !!this.extension && this.extension.installedAt !== null;
        },

        canBeOpened() {
            return this.shopwareExtensionService.canBeOpened(this.extension);
        },

        label() {
            return this.getPropValue('label');
        },

        privacyPolicyLink() {
            return this.getPropValue('privacyPolicyLink');
        },

        /*
         * Interface for deriving components
         */
        permissions() {
            Utils.debug.warn(this._name, 'No implementation of permissions found');
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        isRemovable() {
            if (this.extension && this.extension.installedAt === null) {
                return true;
            }

            return false;
        },

        isUninstallable() {
            if (this.extension && this.extension.installedAt !== null) {
                return true;
            }

            return false;
        },

        isUpdateable() {
            if (this.extension === null) {
                return false;
            }

            if (this.extension.latestVersion === null) {
                return false;
            }

            return this.extension.latestVersion !== this.extension.version;
        }
    },

    methods: {
        emitUpdateList() {
            this.$emit('updateList');
        },

        getPropValue(property) {
            return Utils.get(this.licensedExtension, property) || Utils.get(this.extension, property);
        },

        getHelp() {
            // implemented in SAAS-1137
        },

        openPrivacyAndSafety() {
            window.open(this.getPropValue('privacyPolicyLink'), '_blank');
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
                    this.getPropValue('name'),
                    this.getPropValue('type'),
                    removeData
                );
            } catch (e) {
                console.log(e);
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async updateExtension() {
            this.isLoading = true;

            try {
                if (this.updateLocation === 'store') {
                    await this.shopwareExtensionService.downloadExtension(this.getPropValue('name'));
                }

                await this.shopwareExtensionService.updateExtension(
                    this.getPropValue('name'),
                    this.getPropValue('type')
                );
            } catch (e) {
                this.showSaasErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async closeModalAndRemoveExtension() {
            // we close the modal in the called methods before updating the listing
            if (this.license === null) {
                await this.removeExtension();

                return;
            }

            await this.cancelAndRemoveExtension();
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

            this.permissionModalActionLabel = this.$tc('sw-extension-store.component.sw-extension-card-base.labelAcceptAndInstall');
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

        removeExtension() {
            Utils.debug.warn(this._name, 'No implementation of removeExtension found');
        },

        cancelAndRemoveExtension() {
            Utils.debug.warn(this._name, 'No implementation of cancelAndRemoveExtension found');
        },

        openPrivacyModal() {
            this.showPrivacyModal = true;
        },

        closePrivacyModal() {
            this.showPrivacyModal = false;
        }
    }
});
