import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-shipping-list.html.twig';

Component.register('sw-settings-shipping-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'shipping_method',
            isLoading: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            limit: 10,
            skeletonItemAmount: 3
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-settings-shipping.list.columnName'),
                dataIndex: 'name',
                inlineEdit: 'string',
                routerLink: 'sw.settings.shipping.detail',
                primary: true
            }, {
                property: 'description',
                label: this.$tc('sw-settings-shipping.list.columnDescription'),
                dataIndex: 'description',
                inlineEdit: 'string'
            }];
        },

        onInlineEditSave(item) {
            this.isLoading = true;
            const name = item.name || item.translated.name;

            return item.save().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-shipping.list.titleSaveSuccess'),
                    message: this.$tc('sw-settings-shipping.list.messageSaveSuccess', 0, { name })
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.notification.notificationSaveErrorTitle'),
                    message: this.$tc('sw-settings-shipping.list.messageSaveError', 0, { name })
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onConfirmDelete(id) {
            this.deleteEntity = this.store.store[id];
            const name = this.deleteEntity.name || this.deleteEntity.translated.name;

            this.onCloseDeleteModal();
            this.deleteEntity.delete(true).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-shipping.list.titleSaveSuccess'),
                    message: this.$tc('sw-settings-shipping.list.messageDeleteSuccess', 0, { name })
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.notification.notificationSaveErrorTitle'),
                    message: this.$tc('sw-settings-shipping.list.messageDeleteError', 0, { name })
                });
            }).finally(() => {
                this.deleteEntity = null;
                this.getList();
            });
        }
    }
});
