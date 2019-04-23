import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-customer-group-list.html.twig';

Component.register('sw-settings-customer-group-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'customer_group',
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
                label: this.$tc('sw-settings-customer-group.list.columnName'),
                dataIndex: 'name',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'displayGross',
                label: this.$tc('sw-settings-customer-group.list.columnDisplayGross'),
                dataIndex: 'displayGross',
                inlineEdit: 'boolean'
            }];
        },

        getInlinePlaceholder(entity) {
            return this.placeholder(
                entity,
                'name',
                this.$tc('sw-settings-customer-group.list.fieldNamePlaceholder')
            );
        },

        onInlineEditSave(item) {
            this.isLoading = true;
            const name = item.name;

            return item.save().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-customer-group.general.titleSuccess'),
                    message: this.$tc('sw-settings-customer-group.list.messageSaveSuccess', 0, { name })
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-customer-group.general.titleError'),
                    message: this.$tc('sw-settings-customer-group.list.messageSaveError')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onConfirmDelete(id) {
            this.deleteEntity = this.store.store[id];

            this.onCloseDeleteModal();
            this.deleteEntity.delete(true).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-customer-group.general.titleSuccess'),
                    message: this.$tc('sw-settings-customer-group.list.messageDeleteSuccess')
                });

                this.deleteEntity = null;
                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-customer-group.general.titleError'),
                    message: this.$tc('sw-settings-customer-group.list.messageDeleteError')
                });

                this.deleteEntity = null;
                this.getList();
            });
        }
    }
});
