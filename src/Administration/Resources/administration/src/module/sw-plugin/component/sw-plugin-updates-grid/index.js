import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-updates-grid.html.twig';
import './sw-plugin-updates-grid.scss';

Component.register('sw-plugin-updates-grid', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            limit: 25,
            updates: [],
            isLoading: false,
            updating: [],
            disableRouteParams: false
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    methods: {
        onUpdate(pluginName) {
            this.updating.push(pluginName);
            this.storeService.downloadPlugin(pluginName).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                    message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                });
                this.updating = this.updating.filter(name => name !== pluginName);
                this.getList();
            });
        },

        updateAll() {
            const updatePromises = [];
            this.updates.forEach((update) => {
                updatePromises.push(this.storeService.downloadPlugin(update.name));
                this.updating.push(update.name);
            });
            Promise.all(updatePromises).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                    message: this.$tc('sw-plugin.updates.messageUpdatesSuccess')
                });
                this.updating = [];
                this.getList();
            });
        },

        isUpdating(pluginName) {
            return this.updating.includes(pluginName);
        },

        getList() {
            this.isLoading = true;
            this.storeService.getUpdateList().then((data) => {
                this.updates = data.items;
                this.total = data.total;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('sw-plugin.updates.updateError')
                });
            });
        }
    }
});
