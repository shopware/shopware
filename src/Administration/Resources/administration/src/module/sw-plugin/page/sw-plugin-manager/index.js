import { Component } from 'src/core/shopware';
import template from './sw-plugin-manager.html.twig';
import './sw-plugin-manager.scss';

Component.register('sw-plugin-manager', {
    template,

    inject: ['storeService'],

    data() {
        return {
            searchTerm: '',
            availableUpdates: 0,
            storeAvailable: true
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
            this.fetchAvailableUpdates();
            this.$root.$on('sw-plugin-refresh-updates', (total) => {
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

        successfulUpload() {
            this.$root.$emit('sw-plugin-force-refresh');
        }
    }
});
