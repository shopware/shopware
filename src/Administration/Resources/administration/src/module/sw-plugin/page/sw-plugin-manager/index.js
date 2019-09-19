import template from './sw-plugin-manager.html.twig';
import './sw-plugin-manager.scss';

const { Component } = Shopware;

Component.register('sw-plugin-manager', {
    template,

    inject: ['storeService', 'pluginService', 'licenseViolationService'],

    data() {
        return {
            searchTerm: '',
            availableUpdates: 0,
            storeAvailable: true,
            isLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearch(searchTerm) {
            this.searchTerm = searchTerm;
        },

        createdComponent() {
            this.licenseViolationService.checkForLicenseViolations(true);

            this.fetchAvailableUpdates();
            this.$root.$on('updates-refresh', (total) => {
                if (total) {
                    this.availableUpdates = total;
                    return;
                }
                this.fetchAvailableUpdates();
            });

            this.storeService.ping().then(() => {
                this.storeAvailable = true;
            }).catch(() => {
                this.storeAvailable = false;
            });
        },

        fetchAvailableUpdates() {
            this.storeService.getUpdateList().then((updates) => {
                this.availableUpdates = updates.total;
            });
        },

        reloadPluginListing() {
            this.$root.$emit('force-refresh');
            this.$router.push({ name: 'sw.plugin.index.list' });
        }
    }
});
