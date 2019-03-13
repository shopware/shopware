import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-salutation-list.html.twig';

Component.register('sw-settings-salutation-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            entityName: 'salutation',
            disableRouteParams: true,
            total: 0,
            page: 1,
            limit: 25,
            sortBy: 'salutationKey',
            sortDirection: 'ASC',
            term: '',
            isLoading: false,
            salutations: []
        };
    },

    computed: {
        salutationStore() {
            return State.getStore('salutation');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.salutationStore.getList(params).then(({ items, total }) => {
                this.salutations = items;
                this.total = total;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getInlinePlaceholder(entity) {
            return this.placeholder(
                entity,
                'name',
                this.$tc('sw-settings-salutation.list.fieldNamePlaceholder')
            );
        },

        onPageChange(params) {
            this.page = params.page;
            this.limit = params.limit;
            this.getList();
        },

        onSearch(term) {
            this.term = term;
            this.getList();
        },

        onChangeLanguage() {
            this.getList();
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
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onConfirmDelete(id) {
            const salutation = this.salutationStore.getById(id);
            const key = salutation.salutationKey;

            salutation.delete(true).then(() => {
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

            this.onCloseDeleteModal();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        }
    }
});
