/**
 * @package system-settings
 */
import template from './sw-shopware-updates-plugins.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-shopware-updates-plugins', {
    template,

    inject: ['feature'],

    props: {
        isLoading: {
            type: Boolean,
        },
        plugins: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-settings-shopware-updates.plugins.columns.name'),
                    rawData: true,
                },
                {
                    property: 'icon',
                    label: this.$tc('sw-settings-shopware-updates.plugins.columns.available'),
                    rawData: true,
                },
            ];
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed. The snippet will be used directly in the template
         */
        cardTitle() {
            return this.$tc('sw-settings-shopware-updates.cards.extensions');
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        openPluginManager() {
            this.$router.push({ name: 'sw.plugin.index' });
        },
        openMyExtensions() {
            this.$router.push({ name: 'sw.extension.my-extensions.listing.app' });
        },
    },
});
