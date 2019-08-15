import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { Component } = Shopware;

Component.register('sw-settings-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        hasPluginConfig() {
            return this.$refs.pluginConfig && this.$refs.pluginConfig.childElementCount > 0;
        }
    }
});
