import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-last-updates-grid.html.twig';
import './sw-plugin-last-updates-grid.scss';

Component.register('sw-plugin-last-updates-grid', {
    template,

    inject: ['pluginService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            limit: 25,
            lastUpdates: [],
            isLoading: false
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    methods: {
        onDownload() {
        },

        getList() {
            this.isLoading = true;
            this.pluginService.getLastUpdates().then((data) => {
                this.lastUpdates = data.items;
                this.total = data.total;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('sw-plugin.updates.updateError')
                });
            });
        },

        getLatestChangelog(plugin) {
            const json = JSON.stringify(plugin.changelog);
            const changelogs = JSON.parse(json);

            const latestChangelog = Object.entries(changelogs).pop();

            const changes = [];
            Object.values(latestChangelog).forEach((entry) => {
                changes.push(entry);
            });
            return changes[1];
        }
    }
});
