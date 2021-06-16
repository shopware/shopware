import template from './sw-shopware-updates-plugins.html.twig';

const { Component } = Shopware;

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

        cardTitle() {
            if (this.feature.isActive('FEATURE_NEXT_12608')) {
                return this.$tc('sw-settings-shopware-updates.cards.extensions');
            }

            return this.$tc('sw-settings-shopware-updates.cards.plugins');
        },
    },

    methods: {
        openPluginManager() {
            this.$router.push({ name: 'sw.plugin.index' });
        },
        openMyExtensions() {
            this.$router.push({ name: 'sw.extension.my-extensions.listing.app' });
        },
    },
});
