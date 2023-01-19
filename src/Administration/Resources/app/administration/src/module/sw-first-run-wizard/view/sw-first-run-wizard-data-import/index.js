import template from './sw-first-run-wizard-data-import.html.twig';
import './sw-first-run-wizard-data-import.scss';

const { Criteria } = Shopware.Data;

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'extensionStoreActionService',
        'repositoryFactory',
    ],

    data() {
        return {
            plugins: {
                demodata: {
                    name: 'SwagPlatformDemoData',
                    isInstalled: false,
                },
                migration: {
                    name: 'SwagMigrationAssistant',
                    isInstalled: false,
                },
            },
            demoDataPluginName: 'SwagPlatformDemoData',
            migrationPluginName: 'SwagMigrationAssistant',
            isPluginAlreadyInstalled: false,
            isInstallingPlugin: false,
            installationError: false,
            pluginError: null,
            pluginInstalledSuccessfully: {
                demodata: false,
                migration: false,
            },
        };
    },

    computed: {
        pluginRepository() {
            return this.repositoryFactory.create('plugin');
        },

        buttonConfig() {
            return [
                {
                    key: 'skip',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'sw.first.run.wizard.index.defaults',
                    disabled: this.isInstallingPlugin,
                },
            ];
        },
    },

    watch: {
        isInstallingPlugin() {
            this.updateButtons();
        },
    },


    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
            this.getInstalledPlugins();
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.dataImport.modalTitle'));
        },

        notInstalled(pluginKey) {
            return !this.plugins[pluginKey].isInstalled;
        },

        onInstall(pluginKey) {
            const plugin = this.plugins[pluginKey];
            this.isInstallingPlugin = true;
            this.installationError = false;

            return this.extensionStoreActionService.downloadExtension(plugin.name)
                .then(() => {
                    return this.extensionStoreActionService.installExtension(plugin.name, 'plugin');
                })
                .then(() => {
                    return this.extensionStoreActionService.activateExtension(plugin.name, 'plugin');
                })
                .then(() => {
                    this.$emit('extension-activated');
                    this.isInstallingPlugin = false;
                    this.plugins[pluginKey].isInstalled = true;

                    return false;
                })
                .catch((error) => {
                    this.isInstallingPlugin = false;
                    this.installationError = true;

                    if (error.response?.data?.errors) {
                        this.pluginError = error.response.data.errors.pop();
                    }

                    return true;
                });
        },

        getInstalledPlugins() {
            const pluginNames = Object.values(this.plugins).map(plugin => plugin.name);
            const pluginCriteria = new Criteria(1, 5);

            pluginCriteria
                .addFilter(
                    Criteria.equalsAny('plugin.name', pluginNames),
                );

            this.pluginRepository.search(pluginCriteria)
                .then((result) => {
                    if (result.total < 1) {
                        return;
                    }

                    result.forEach((plugin) => {
                        if (!plugin.active || plugin.installedAt === null) {
                            return;
                        }

                        const key = this.findPluginKeyByName(plugin.name);

                        this.plugins[key].isInstalled = true;
                    });
                });
        },

        findPluginKeyByName(name) {
            const [pluginKey] = Object.entries(this.plugins).find(([key, state]) => {
                if (state.name === name) {
                    return key;
                }

                return '';
            });

            return pluginKey;
        },
    },
};
