import template from './sw-extension-my-extensions-listing.html.twig';
import './sw-extension-my-extensions-listing.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-my-extensions-listing', {
    template,

    inject: ['shopwareExtensionService'],

    computed: {
        isLoading() {
            const state = Shopware.State.get('shopwareExtensions');

            return state.myExtensions.loading;
        },

        myExtensions() {
            return Shopware.State.get('shopwareExtensions').myExtensions.data;
        },

        extensionList() {
            const isAppRoute = this.$route.name === 'sw.extension.my-extensions.listing.app';
            const isThemeRoute = this.$route.name === 'sw.extension.my-extensions.listing.theme';

            return this.myExtensions.filter(extension => {
                // app route and no theme
                if (isAppRoute && !extension.isTheme) {
                    return true;
                }

                // theme route and theme
                if (isThemeRoute && extension.isTheme) {
                    return true;
                }

                return false;
            });
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
        }
    }
});
