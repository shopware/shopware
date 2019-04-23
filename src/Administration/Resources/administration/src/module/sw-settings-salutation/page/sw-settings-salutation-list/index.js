import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-salutation-list.html.twig';

Component.register('sw-settings-salutation-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'salutation',
            isLoading: false,
            limit: 10,
            sortBy: 'salutationKey',
            sortDirection: 'ASC',
            skeletonItemAmount: 5
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
                property: 'salutationKey',
                label: this.$tc('sw-settings-salutation.list.columnSalutationKey'),
                dataIndex: 'salutationKey',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'displayName',
                label: this.$tc('sw-settings-salutation.list.columnDisplayName'),
                dataIndex: 'displayName',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'letterName',
                label: this.$tc('sw-settings-salutation.list.columnLetterName'),
                dataIndex: 'letterName',
                inlineEdit: 'string'
            }];
        },

        getInlinePlaceholder(entity, field = 'displayName') {
            return this.placeholder(
                entity,
                field,
                this.$tc('sw-settings-salutation.list.fieldDisplayNamePlaceholder')
            );
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            if (item.salutationKey === null || item.salutationKey.trim() === '') {
                this.inlineError();
                return;
            }

            item.save().then(() => {
                this.inlineSuccess(item.salutationKey);
            }).catch(() => {
                item.discardChanges();
                this.inlineError();
            });
        },

        onConfirmDelete(id) {
            const salutation = this.store.getById(id);
            const key = salutation.salutationKey;

            this.onCloseDeleteModal();
            return salutation.delete(true).then(() => {
                this.getList();

                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-salutation.general.titleSuccess'),
                    message: this.$tc('sw-settings-salutation.list.messageDeleteSuccess', 0, { key })
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-salutation.general.titleError'),
                    message: this.$tc('sw-settings-salutation.list.messageDeleteError')
                });
            });
        },

        inlineSuccess(key) {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-salutation.general.titleSuccess'),
                message: this.$tc('sw-settings-salutation.list.messageSaveSuccess', 0, { key })
            });

            this.isLoading = false;
        },

        inlineError() {
            this.createNotificationError({
                title: this.$tc('sw-settings-salutation.general.titleError'),
                message: this.$tc('sw-settings-salutation.list.messageSaveError')
            });

            this.isLoading = false;
        }
    }
});
