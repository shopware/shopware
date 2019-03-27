import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-plugin-list.html.twig';
import './sw-plugin-list.scss';

Component.register('sw-plugin-list', {
    template,

    props: {
        searchTerm: {
            type: String,
            required: false
        }
    },

    inject: ['pluginService', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            limit: 25,
            plugins: [],
            isLoading: false,
            sortType: 'upgradedAt:asc'
        };
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        pluginsStore() {
            return State.getStore('plugin');
        },

        showPagination() {
            return (this.total >= 25);
        }
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        },

        searchTerm() {
            this.onSearch(this.searchTerm);
        }
    },

    methods: {
        mountedComponent() {
            this.$root.$on('sw-plugin-force-refresh', () => {
                this.getList();
            });
        },

        changeActiveState(plugin) {
            if (!plugin.active) {
                this.pluginService.deactivate(plugin.name).then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.list.titleDeactivateSuccess'),
                        message: this.$tc('sw-plugin.list.messageDeactivateSuccess')
                    });
                });
            } else {
                this.pluginService.activate(plugin.name).then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.list.titleActivateSuccess'),
                        message: this.$tc('sw-plugin.list.messageActivateSuccess')
                    });
                });
            }
        },

        onInstallPlugin(plugin) {
            this.pluginService.install(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleInstallSuccess'),
                    message: this.$tc('sw-plugin.list.messageInstallSuccess')
                });
                this.getList();
            });
        },

        onUninstallPlugin(plugin) {
            this.pluginService.uninstall(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleUninstallSuccess'),
                    message: this.$tc('sw-plugin.list.messageUninstallSuccess')
                });
                this.getList();
            });
        },

        onUpdatePlugin(plugin) {
            this.pluginService.update(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleUpdateSuccess'),
                    message: this.$tc('sw-plugin.list.messageUpdateSuccess')
                });
                this.getList();
            });
        },

        onDeletePlugin(plugin) {
            this.pluginService.delete(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleDeleteSuccess'),
                    message: this.$tc('sw-plugin.list.messageDeleteSuccess')
                });
                this.getList();
                this.$root.$emit('sw-plugin-refresh-updates');
            });
        },

        onPluginSettings(plugin) {
            this.$router.push({ name: 'sw.plugin.settings', params: { namespace: plugin.name } });
        },

        successfulUpload() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            const params = this.getListingParams();

            this.pluginsStore.getList(params).then((response) => {
                this.plugins = response.items;
                this.isConfigAvailableForPlugins();
                this.total = response.total;
                this.isLoading = false;
            });
        },

        getListingParams() {
            const sortType = this.sortType.split(':');

            return {
                limit: this.limit,
                page: this.page,
                sortBy: sortType[0],
                sortDirection: sortType[1],
                term: this.term
            };
        },

        sortPluginList(event) {
            this.sortType = event;
            this.page = 1;
            this.getList();
        },

        isConfigAvailableForPlugins() {
            this.plugins.forEach((plugin) => {
                if (!plugin.active) {
                    return;
                }

                // TODO: replace n requests with one request
                this.getConfig(plugin.name).then((returnedConfig) => {
                    plugin.attributes = {
                        config: returnedConfig[0]
                    };
                }).catch(() => {
                    // nth
                });
            });
        },

        getConfig(pluginName) {
            return this.systemConfigApiService.getConfig(`bundle.${pluginName}`);
        }
    }
});
