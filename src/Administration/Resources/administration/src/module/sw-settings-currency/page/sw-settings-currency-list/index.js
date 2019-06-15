import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-currency-list.html.twig';

Component.register('sw-settings-currency-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [

        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('listing')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            entityName: 'currency',
            currency: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'DESC',
            naturalSorting: true
        };
    },

    computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        }
    },

    methods: {
        metaInfo() {
            return {
                title: this.$createTitle()
            };
        },

        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.currencyRepository.search(criteria, this.context).then((items) => {
                this.total = items.total;
                this.currency = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(promise, currency) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-currency.detail.titleSaveSuccess'),
                    message: this.$tc('sw-settings-currency.detail.messageSaveSuccess', 0, { name: currency.name })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-settings-currency.detail.titleSaveError'),
                    message: this.$tc('sw-settings-currency.detail.messageSaveError')
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

            return this.currencyRepository.delete(id, this.context).then(() => {
                this.getList();
            });
        },


        getCurrencyColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: this.$tc('sw-settings-currency.list.columnName'),
                routerLink: 'sw.settings.currency.detail',
                width: '250px',
                primary: true
            }, {
                property: 'isoCode',
                label: this.$tc('sw-settings-currency.list.columnIsoCode')
            }, {
                property: 'shortName',
                label: this.$tc('sw-settings-currency.list.columnShortName')
            }, {
                property: 'symbol',
                label: this.$tc('sw-settings-currency.list.columnSymbol')
            }, {
                property: 'factor',
                label: this.$tc('sw-settings-currency.list.columnFactor')
            }];
        }
    }
});
