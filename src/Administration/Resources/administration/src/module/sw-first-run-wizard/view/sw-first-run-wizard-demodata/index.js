import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-demodata.html.twig';
import './sw-first-run-wizard-demodata.scss';

Component.register('sw-first-run-wizard-demodata', {
    template,

    inject: ['addNextCallback', 'storeService', 'pluginService'],

    data() {
        return {
            isInstallingPlugin: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addNextCallback(this.installDemodata);
        },

        installDemodata() {
            const language = this.$store.state.adminLocale.currentLocale;
            const pluginName = language === 'de_DE'
                ? 'SwagPlatformDemoDataDE'
                : 'SwagPlatformDemoDataEN';

            this.isInstallingPlugin = true;

            return this.storeService.downloadPlugin(pluginName, true)
                .then(() => {
                    return this.pluginService.install(pluginName);
                })
                .then(() => {
                    return this.pluginService.activate(pluginName);
                })
                .catch(() => {
                    this.isInstallingPlugin = false;

                    return true;
                });
        }
    }
});
