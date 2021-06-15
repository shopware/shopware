import template from './sw-settings-tax-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-tax-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            tax: null,
            sortBy: 'position',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: false,
            showDeleteModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        taxRepository() {
            return this.repositoryFactory.create('tax');
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            if (this.sortBy !== 'name') {
                // Add second sorting, to make sorting deterministic
                criteria.addSorting(Criteria.sort('name', 'ASC', true));
            }

            this.taxRepository.search(criteria).then((items) => {
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
                    message: this.$tc('sw-settings-tax.detail.messageSaveSuccess', 0, { name: tax.name }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-settings-tax.detail.messageSaveError'),
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

            return this.taxRepository.delete(id).then(() => {
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
                primary: true,
            }, {
                property: 'taxRate',
                inlineEdit: 'number',
                label: 'sw-settings-tax.list.columnDefaultTaxRate',
            }];
        },

        isShopwareDefaultTax(tax) {
            return this.$te(`global.tax-rates.${tax.name}`, 'en-GB');
        },

        getLabel(tax) {
            return this.isShopwareDefaultTax(tax) ? this.$tc(`global.tax-rates.${tax.name}`) : tax.name;
        },
    },
});
