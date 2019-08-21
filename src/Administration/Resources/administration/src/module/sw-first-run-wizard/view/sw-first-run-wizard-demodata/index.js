import template from './sw-first-run-wizard-demodata.html.twig';
import './sw-first-run-wizard-demodata.scss';

const { Component } = Shopware;

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
            const pluginName = 'SwagPlatformDemoData';

            this.isInstallingPlugin = true;

            return this.storeService.downloadPlugin(pluginName, true)
                .then(() => {
                    return this.pluginService.install(pluginName);
                })
                .then(() => {
                    return this.pluginService.activate(pluginName);
                })
                .then(() => {
                    this.isInstallingPlugin = false;

                    return false;
                })
                .catch(() => {
                    this.isInstallingPlugin = false;

                    return true;
                });
        }
    }
});
