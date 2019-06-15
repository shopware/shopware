import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-tax-list.html.twig';

Component.register('sw-settings-tax-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            tax: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true
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

            this.taxRepository.search(criteria, this.context).then((items) => {
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

            return this.taxRepository.delete(id, this.context).then(() => {
                this.getList();
            });
        },


        getTaxColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: this.$tc('sw-settings-tax.list.columnName'),
                routerLink: 'sw.settings.tax.detail',
                width: '250px',
                primary: true
            }, {
                property: 'taxRate',
                label: this.$tc('sw-settings-tax.list.columnTaxRate')
            }];
        }
    }
});
