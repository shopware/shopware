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
            disableRouteParams: false,
            updateQueue: []
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        },

        updateQueue() {
            this.updateQueue.forEach((update) => {
                update.downloadAction.then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                        message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                    });
                    this.getList();
                    this.$root.$emit('sw-plugin-refresh-updates');
                    this.updateQueue = this.updateQueue.filter(queueObj => queueObj.pluginName !== update.pluginName);
                });
            });
        }
    },

    methods: {
        onUpdate(pluginName) {
            const queueObj = {
                pluginName: pluginName,
                downloadAction: this.storeService.downloadPlugin(pluginName)
            };
            this.updateQueue.push(queueObj);
        },

        updateAll() {
            this.updates.forEach((update) => {
                const queueObj = {
                    pluginName: update.name,
                    downloadAction: this.storeService.downloadPlugin(update.name)
                };
                this.updateQueue.push(queueObj);
            });
        },

        cancelUpdates() {
            this.updateQueue = [];
        },

        isUpdating(pluginName) {
            return this.updateQueue.some(element => element.pluginName === pluginName);
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
