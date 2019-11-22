import template from './sw-first-run-wizard-demodata.html.twig';
import './sw-first-run-wizard-demodata.scss';

const { Criteria } = Shopware.Data;
const { Component } = Shopware;

Component.register('sw-first-run-wizard-demodata', {
    template,

    inject: [
        'addNextCallback',
        'storeService',
        'pluginService',
        'repositoryFactory'
    ],

    data() {
        return {
            demodataPluginName: 'SwagPlatformDemoData',
            isPluginAlreadyInstalled: false,
            isInstallingPlugin: false,
            showInstallWarning: false,
            installWarningPromise: null
        };
    },

    computed: {
        pluginRepository() {
            return this.repositoryFactory.create('plugin');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getInstalledPlugins();
            this.addNextCallback(this.installDemodata);
        },

        installDemodata() {
            // check if plugin is already installed
            if (this.isPluginAlreadyInstalled) {
                // show warning
                this.showInstallWarning = true;

                // create global promise
                this.installWarningPromise = this.createPromise();

                // return global promise which can be resolved from outside
                return this.installWarningPromise;
            }

            return this.installPlugin();
        },

        cancelInstallation() {
            this.showInstallWarning = false;

            // cancel installation
            this.installWarningPromise.resolve(true);
            this.installWarningPromise = null;
        },

        continueInstallation() {
            this.showInstallWarning = false;

            // install Plugin
            this.installPlugin().then(() => {
                // resolve with success
                this.installWarningPromise.resolve(false);
                this.installWarningPromise = null;
            });
        },

        installPlugin() {
            this.isInstallingPlugin = true;

            const pluginName = this.demodataPluginName;

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
        },

        getInstalledPlugins() {
            const pluginCriteria = new Criteria();

            pluginCriteria
                .addFilter(
                    Criteria.equals('plugin.name', this.demodataPluginName)
                )
                .setLimit(1);

            this.pluginRepository
                .search(pluginCriteria, Shopware.Context.api)
                .then((result) => {
                    if (result.total < 1) {
                        return;
                    }

                    this.isPluginAlreadyInstalled = true;
                });
        },

        createPromise() {
            // create promise which can be resolved from outside
            let _resolve;

            const newPromise = new Promise((resolve) => {
                _resolve = resolve;
            });

            newPromise.resolve = _resolve;

            return newPromise;
        }
    }
});
