import template from './sw-shopware-updates-plugins.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-shopware-updates-plugins', {
    template,

    props: {
        isLoading: {
            type: Boolean
        },
        plugins: {
            type: Array
        }
    },

    methods: {
        openPluginManager() {
            this.$router.push({ name: 'sw.plugin.index' });
        }
    },
    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-settings-shopware-updates.plugins.columns.name'),
                    rawData: true
                },
                {
                    property: 'icon',
                    label: this.$tc('sw-settings-shopware-updates.plugins.columns.available'),
                    rawData: true
                }
            ];
        }
    }
});
