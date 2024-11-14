import template from './sw-shopware-updates-plugins.html.twig';

const { Component } = Shopware;

/**
 * @package services-settings
 * @private
 */
Component.register('sw-settings-shopware-updates-plugins', {
    template,

    compatConfig: Shopware.compatConfig,

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
    },

    methods: {
        openMyExtensions() {
            this.$router.push({
                name: 'sw.extension.my-extensions.listing.app',
            });
        },
    },
});
