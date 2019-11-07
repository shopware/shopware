const { Mixin, StateDeprecated } = Shopware;
const { debug } = Shopware.Utils;

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
            showDeleteModal: false,
            deleteEntity: null,
            steps: [10, 25, 50]
        };
    },

    computed: {
        store() {
            return StateDeprecated.getStore(this.entityName);
        },
        titleSaveSuccess() {
            return this.$tc(`sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.titleDeleteSuccess`);
        },
        messageSaveSuccess() {
            if (this.deleteEntity) {
                let name = this.deleteEntity.name;
                if (this.deleteEntity.hasOwnProperty('translated') && this.deleteEntity.translated.hasOwnProperty('name')) {
                    name = this.deleteEntity.translated.name;
                }

                return this.$tc(
                    `sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.messageDeleteSuccess`,
                    0,
                    { name: name }
                );
            }
            return '';
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
            this.deleteEntity = this.store.store[id];

            this.onCloseDeleteModal();
            return this.store.store[id].delete(true).then(() => {
                this.createNotificationSuccess({
                    title: this.titleSaveSuccess,
                    message: this.messageSaveSuccess
                });

                this.deleteEntity = null;
                this.getList();
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            return item.save().then(() => {
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
