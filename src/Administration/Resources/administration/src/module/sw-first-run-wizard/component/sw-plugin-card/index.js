import { Component } from 'src/core/shopware';
import template from './sw-plugin-card.html.twig';
import './sw-plugin-card.scss';

Component.register('sw-plugin-card', {
    template,

    inject: ['storeService', 'pluginService'],

    props: {
        plugin: {
            type: Object,
            required: true
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

        onUninstall() {
            this.removePlugin();
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
                    document.location.reload();
                });
        },

        removePlugin() {
            const pluginName = this.plugin.name;

            this.pluginService.uninstall(pluginName)
                .then(() => {
                    document.location.reload();
                });
        }
    }
});
