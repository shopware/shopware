import template from './sw-extension-store-purchased.html.twig';
import './sw-extension-store-purchased.scss';

const { Component } = Shopware;

Component.register('sw-extension-store-purchased', {
    template,

    inject: ['shopwareExtensionService'],

    computed: {
        isLoading() {
            const state = Shopware.State.get('shopwareExtensions');

            return state.licensedExtensions.loading || state.installedExtensions.loading;
        },

        licensedExtensions() {
            return Shopware.State.get('shopwareExtensions').licensedExtensions.data;
        },

        installedExtensions() {
            return Shopware.State.get('shopwareExtensions').installedExtensions.data.reduce((acc, extension) => {
                acc[extension.name.toLowerCase()] = extension;

                return acc;
            }, {});
        },

        extensionList() {
            const installedExtensions = Object.assign({}, this.installedExtensions);
            const sortedActiveExtensions = [];
            const sortedInstalledExtensions = [];
            const sortedOtherExtensions = [];


            this.licensedExtensions.forEach(license => {
                let extension = null;
                let updateLocation = null;
                if (installedExtensions.hasOwnProperty(license.licensedExtension.name.toLowerCase())) {
                    extension = installedExtensions[license.licensedExtension.name.toLowerCase()];
                    if (extension.latestVersion) {
                        updateLocation = 'local';
                    } else if (license.licensedExtension.latestVersion && extension.version !== license.licensedExtension.latestVersion) {
                        updateLocation = 'store';
                    }
                }

                const item = {
                    license,
                    extension,
                    key: license.licensedExtension.name,
                    isLocalAvailable: extension !== null,
                    updateLocation
                };

                if (extension && extension.active) {
                    sortedActiveExtensions.push(item);
                } else if (extension && extension.installedAt !== null) {
                    sortedInstalledExtensions.push(item);
                } else {
                    sortedOtherExtensions.push(item);
                }

                delete installedExtensions[license.licensedExtension.name.toLowerCase()];
            });

            Object.entries(installedExtensions).forEach((extension) => {
                extension = extension[1];

                const item = {
                    license: null,
                    extension,
                    key: extension.name,
                    isLocalAvailable: true,
                    updateLocation: 'local'
                };

                if (extension.active) {
                    sortedActiveExtensions.push(item);
                } else if (extension.installedAt !== null) {
                    sortedInstalledExtensions.push(item);
                } else {
                    sortedOtherExtensions.push(item);
                }
            });

            this.sortByLocale(sortedActiveExtensions);
            this.sortByLocale(sortedInstalledExtensions);
            this.sortByLocale(sortedOtherExtensions);
            const allExtensions = [].concat(sortedActiveExtensions, sortedInstalledExtensions, sortedOtherExtensions);
            const listExtension = [];

            allExtensions.forEach(extension => {
                const isTheme = (extension.extension ? extension.extension.isTheme : false) || (extension.license ? extension.license.licensedExtension.isTheme : false);

                if (this.$route.name === 'sw.extension.store.purchased.app' && !isTheme) {
                    listExtension.push(extension);
                } else if (this.$route.name === 'sw.extension.store.purchased.theme' && isTheme) {
                    listExtension.push(extension);
                }
            });

            return listExtension;
        }
    },

    methods: {
        updateList() {
            this.shopwareExtensionService.updateExtensionData();
        },

        openStore() {
            this.$router.push({
                name: 'sw.extension.store.index'
            });
        },

        getTitle(extension) {
            if (extension.extension) {
                return extension.extension.label;
            }

            return extension.license.licensedExtension.label;
        },

        sortByLocale(array) {
            return array.sort((a, b) => this.getTitle(a).localeCompare(this.getTitle(b)));
        }
    }
});
