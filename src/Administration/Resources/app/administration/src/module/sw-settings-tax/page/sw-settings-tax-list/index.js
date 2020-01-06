import template from './sw-settings-tax-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-tax-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            tax: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false
        };
    },

    computed: {
        taxRepository() {
            return this.repositoryFactory.create('tax');
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'tax.name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.taxRepository.search(criteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.tax = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(promise, tax) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-tax.detail.titleSaveSuccess'),
                    message: this.$tc('sw-settings-tax.detail.messageSaveSuccess', 0, { name: tax.name })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-settings-tax.detail.titleSaveError'),
                    message: this.$tc('sw-settings-tax.detail.messageSaveError')
                });
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.taxRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },

        getTaxColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-tax.list.columnName',
                routerLink: 'sw.settings.tax.detail',
                width: '250px',
                primary: true
            }, {
                property: 'defaultTaxRate',
                inlineEdit: 'number',
                label: 'sw-settings-tax.list.columnDefaultTaxRate'
            }];
        }
    }
});
