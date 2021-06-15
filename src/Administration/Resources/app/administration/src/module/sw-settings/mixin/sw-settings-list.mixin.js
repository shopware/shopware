const { Mixin, Data: { Criteria } } = Shopware;
const { debug } = Shopware.Utils;

Mixin.register('sw-settings-list', {

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            entityName: '',
            items: [],
            isLoading: false,
            showDeleteModal: false,
            deleteEntity: null,
            steps: [10, 25, 50],
        };
    },

    computed: {
        entityRepository() {
            return this.repositoryFactory.create(this.entityName);
        },
        listingCriteria() {
            const criteria = new Criteria();

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },
        titleSaveSuccess() {
            if (this.$te(`sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.titleDeleteSuccess`)) {
                return this.$tc((`sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.titleDeleteSuccess`));
            }

            return this.$tc('global.default.success');
        },
        messageSaveSuccess() {
            if (this.deleteEntity) {
                let name = this.deleteEntity.name;
                if (this.deleteEntity.hasOwnProperty('translated') && this.deleteEntity.translated.hasOwnProperty('name')) {
                    name = this.deleteEntity.translated.name;
                }

                if (this.$te(`sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.messageDeleteSuccess)`)) {
                    return this.$tc(
                        `sw-settings-${this.entityName.replace(/[_]/g, '-')}.list.messageDeleteSuccess`,
                        0,
                        { name: name },
                    );
                }

                return this.$tc(
                    'global.notification.messageDeleteSuccess',
                    0,
                    { name: name },
                );
            }
            return '';
        },
    },

    created() {
        if (this.entityName === '') {
            debug.warn('sw-settings-list mixin', 'You need to define the data property "entityName".');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            this.entityRepository.search(this.listingCriteria)
                .then((items) => {
                    this.items = items;
                    this.total = items.total;

                    return this.items;
                })
                .finally(() => {
                    this.isLoading = false;
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
            this.deleteEntity = this.items.find((item) => item.id === id);

            this.onCloseDeleteModal();
            return this.entityRepository.delete(id)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.titleSaveSuccess,
                        message: this.messageSaveSuccess,
                    });
                })
                .finally(() => {
                    this.deleteEntity = null;
                    this.getList();
                });
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            return this.entityRepository.save(item)
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onInlineEditCancel() {
            this.getList();
        },
    },
});
