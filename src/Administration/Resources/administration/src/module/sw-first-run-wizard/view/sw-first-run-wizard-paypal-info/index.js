import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-paypal-info.html.twig';
import './sw-first-run-wizard-paypal-info.scss';

Component.register('sw-first-run-wizard-paypal-info', {
    template,

    inject: ['addNextCallback', 'storeService', 'pluginService'],

    data() {
        return {
            isInstallingPlugin: false,
            pluginInstallationFailed: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addNextCallback(this.installPayPal);
        },

        installPayPal() {
            const pluginName = 'SwagPayPal';

            this.isInstallingPlugin = true;

            return this.storeService.downloadPlugin(pluginName, true)
                .then(() => {
                    return this.pluginService.install(pluginName);
                })
                .then(() => {
                    return this.pluginService.activate(pluginName);
                })
                .then(() => {
                    // need a force reload, after plugin was activated
                    const { origin, pathname } = document.location;
                    const url = `${origin}${pathname}/#/sw/first/run/wizard/index/paypal/credentials`;

                    document.location.href = url;

                    return Promise.resolve(true);
                })
                .catch(() => {
                    this.isInstallingPlugin = false;
                    this.pluginInstallationFailed = true;

                    return true;
                });
        }
    }
});
