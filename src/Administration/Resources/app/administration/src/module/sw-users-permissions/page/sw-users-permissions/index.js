import template from './sw-users-permissions.html.twig';

const { Component, FeatureConfig } = Shopware;

Component.register('sw-users-permissions', {
    template,

    data() {
        return {
            FeatureConfig: FeatureConfig
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {},

    methods: {}
});
