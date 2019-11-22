import template from './sw-plugin-card.html.twig';
import './sw-plugin-card.scss';

const { Component } = Shopware;

Component.register('sw-plugin-card', {
    template,

    inject: ['storeService', 'pluginService', 'cacheApiService'],

    props: {
        plugin: {
            type: Object,
            required: true
        },
        showDescription: {
            type: Boolean,
            default: true,
            required: false
        }
    },

    data() {
        return {
            pluginIsLoading: false,
            pluginIsSaveSuccessful: false
        };
    },

    computed: {
        pluginIsNotActive() {
            return !this.plugin.active;
        }
    },

    methods: {
        onInstall() {
            this.setupPlugin();
        },

        setupPlugin() {
            const pluginName = this.plugin.name;

            this.pluginIsLoading = true;
            this.pluginIsSaveSuccessful = false;

            return this.storeService.downloadPlugin(pluginName, true)
                .then(() => {
                    this.pluginIsSaveSuccessful = true;
                    return this.pluginService.install(pluginName);
                })
                .then(() => {
                    return this.pluginService.activate(pluginName);
                })
                .finally(() => {
                    this.pluginIsLoading = false;
                    this.cacheApiService.clear();

                    this.$emit('onPluginInstalled', pluginName);
                });
        }
    }
});
