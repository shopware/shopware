import { State, Mixin } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';

Mixin.register('sw-settings-list', {

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            entityName: '',
            items: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        store() {
            return State.getStore(this.entityName);
        }
    },

    created() {
        if (this.entityName === '') {
            debug.warn('sw-settings-list mixin', 'You need to define the data property "entityName".');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.items = [];

            return this.store.getList(params).then((response) => {
                this.total = response.total;
                this.items = response.items;
                this.isLoading = false;

                return this.items;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const currency = this.store.store[id];
            const currencyName = currency.name;
            const titleSaveSuccess = this.$tc(`sw-settings-${this.entityName}.list.titleDeleteSuccess`);
            const messageSaveSuccess = this.$tc(
                `sw-settings-${this.entityName}.list.messageDeleteSuccess`,
                0,
                { name: currencyName }
            );

            this.onCloseDeleteModal();
            this.store.store[id].delete(true).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.getList();
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            item.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditCancel(item) {
            item.discardChanges();
        }
    }
});
