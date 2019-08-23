import template from './sw-first-run-wizard-paypal-info.html.twig';
import './sw-first-run-wizard-paypal-info.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-paypal-info', {
    template,

    inject: ['addNextCallback', 'storeService', 'pluginService'],

    data() {
        return {
            isInstallingPlugin: false,
            pluginInstallationFailed: false,
            pluginName: 'SwagPayPal',
            installPromise: Promise.resolve()
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addNextCallback(this.activatePayPalAndRedirect);
            this.installPromise = this.installPayPal();
        },

        installPayPal() {
            return this.storeService.downloadPlugin(this.pluginName, true)
                .then(() => {
                    return this.pluginService.install(this.pluginName);
                });
        },

        activatePayPalAndRedirect() {
            this.isInstallingPlugin = true;
            this.installPromise.then(() => {
                return this.pluginService.activate(this.pluginName);
            }).then(() => {
                // need a force reload, after plugin was activated
                const { origin, pathname } = document.location;
                const url = `${origin}${pathname}/#/sw/first/run/wizard/index/paypal/credentials`;

                document.location.href = url;

                return Promise.resolve(true);
            }).catch(() => {
                this.isInstallingPlugin = false;
                this.pluginInstallationFailed = true;

                return true;
            });
        }
    }
});
