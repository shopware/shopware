import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-welcome.html.twig';
import './sw-first-run-wizard-welcome.scss';

Component.register('sw-first-run-wizard-welcome', {
    template,

    inject: ['languagePluginService'],

    data() {
        return {
            languagePlugins: []
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getLanguagePlugins();
        },

        getLanguagePlugins() {
            // ToDo: (mve) add param of current language
            this.languagePluginService.getPlugins().then((response) => {
                this.languagePlugins = response.items;
            });
        }
    }
});
