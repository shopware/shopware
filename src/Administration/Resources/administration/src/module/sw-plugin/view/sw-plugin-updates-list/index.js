import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-updates-list.twig';
import './sw-plugin-updates-list.scss';

Component.register('sw-plugin-updates-list', {
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
            isLoading: false
        };
    },

    computed: {
        showPagination() {
            return (this.total >= 25);
        }
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
            this.storeService.getUpdateList().then((data) => {
                this.updates = data.items;
                this.total = data.total;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('sw-plugin.updates-list.updateError')
                });
            });
        }
    }
});
