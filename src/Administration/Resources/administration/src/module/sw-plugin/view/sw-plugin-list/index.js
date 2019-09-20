import { mapState } from 'vuex';
import template from './sw-plugin-list.html.twig';
import './sw-plugin-list.scss';

const { Component, Mixin, State } = Shopware;

Component.register('sw-plugin-list', {
    template,

    props: {
        searchTerm: {
            type: String,
            required: false
        },

        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    inject: ['pluginService', 'systemConfigApiService', 'context', 'cacheApiService', 'licenseViolationService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            limit: 25,
            plugins: [],
            isLoading: false,
            sortBy: 'upgradedAt',
            sortDirection: 'desc',
            sortType: 'upgradedAt:desc',
            showDeleteModal: false
        };
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        ...mapState('licenseViolation', [
            'violations',
            'warnings',
            'other'
        ]),

        pluginsStore() {
            return State.getStore('plugin');
        },

        showPagination() {
            return (this.total >= 25);
        },

        currentLocale() {
            return this.$store.state.adminLocale.currentLocale;
        },

        languageId() {
            return this.$store.state.adminLocale.languageId;
        }
    },

    watch: {
        currentLocale() {
            this.getList();
        },

        searchTerm() {
            this.onSearch(this.searchTerm);
        },

        languageId() {
            this.getList();
        }
    },

    methods: {
        mountedComponent() {
            this.$root.$on('force-refresh', () => {
                this.getList();
            });

            // force reload of license violations
            this.licenseViolationService.removeTimeFromLocalStorage(this.licenseViolationService.key.lastLicenseFetchedKey);
        },

        changeActiveState(plugin, event) {
            plugin.active = event;
            if (!plugin.active) {
                this.pluginService.deactivate(plugin.name).then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.list.titleDeactivateSuccess'),
                        message: this.$tc('sw-plugin.list.messageDeactivateSuccess')
                    });
                    this.getList();
                    this.cacheApiService.clear();

                    window.location.reload();
                });
            } else {
                this.pluginService.activate(plugin.name).then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.list.titleActivateSuccess'),
                        message: this.$tc('sw-plugin.list.messageActivateSuccess')
                    });
                    this.getList();
                    this.cacheApiService.clear();

                    window.location.reload();
                });
            }
        },

        onInstallPlugin(plugin) {
            this.isLoading = true;
            this.pluginService.install(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleInstallSuccess'),
                    message: this.$tc('sw-plugin.list.messageInstallSuccess')
                });
                this.getList();
            });
        },

        onUninstallPlugin(plugin) {
            this.isLoading = true;
            this.pluginService.uninstall(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleUninstallSuccess'),
                    message: this.$tc('sw-plugin.list.messageUninstallSuccess')
                });
                this.getList();
                this.cacheApiService.clear();

                // Reload if plugin gets uninstalled while active
                if (plugin.active === true) {
                    window.location.reload();
                }
            });
        },

        onUpdatePlugin(plugin) {
            this.isLoading = true;
            this.pluginService.update(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleUpdateSuccess'),
                    message: this.$tc('sw-plugin.list.messageUpdateSuccess')
                });
                this.getList();
                this.cacheApiService.clear();
            });
        },

        onDeletePlugin(plugin) {
            this.showDeleteModal = plugin.id;
        },

        onConfirmDelete(plugin) {
            this.isLoading = true;
            this.pluginService.delete(plugin.name).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleDeleteSuccess'),
                    message: this.$tc('sw-plugin.list.messageDeleteSuccess')
                });
                this.getList();
                this.$root.$emit('updates-refresh');
                this.cacheApiService.clear();

                this.showDeleteModal = false;
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onPluginSettings(plugin) {
            this.$router.push({ name: 'sw.plugin.settings', params: { namespace: plugin.name } });
        },

        successfulUpload() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.pluginService.refresh().then(() => {
                const params = this.getListingParams();

                return this.pluginsStore.getList(
                    params,
                    false,
                    this.$store.state.adminLocale.languageId
                );
            }).then((response) => {
                this.plugins = response.items;
                this.isConfigAvailableForPlugins();
                this.total = response.total;
                this.isLoading = false;
            });
        },

        getListingParams() {
            return {
                limit: this.limit,
                page: this.page,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
                term: this.term
            };
        },

        sortPluginList(event) {
            this.sortType = event;
            const sorting = this.sortType.split(':');
            this.sortBy = sorting[0];
            this.sortDirection = sorting[1];
            this.page = 1;
            this.updateRoute({
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
                page: this.page
            });
            this.getList();
        },

        isConfigAvailableForPlugins() {
            this.plugins.forEach((plugin) => {
                if (!plugin.active) {
                    return;
                }

                this.systemConfigApiService.checkConfig(`${plugin.name}.config`).then((response) => {
                    plugin.customFields = {
                        configAvailable: response
                    };
                }).catch(() => {
                    plugin.customFields = {
                        configAvailable: false
                    };
                });
            });
        },

        getLicenseInformationForPlugin(plugin) {
            const matches = [
                ...this.violations.filter((violation) => violation.name === plugin.name),
                ...this.warnings.filter((warning) => warning.name === plugin.name),
                ...this.other.filter((warning) => warning.name === plugin.name)
            ];

            return matches.map((match) => {
                return {
                    level: match.type.level,
                    label: match.type.label,
                    text: match.text,
                    actions: match.actions
                };
            });
        }
    }
});
