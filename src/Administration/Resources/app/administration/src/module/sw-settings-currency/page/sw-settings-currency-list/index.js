import template from './sw-settings-currency-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-currency-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
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
            sortBy: 'currency.name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false
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

            this.currencyRepository.search(criteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.currency = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.StateDeprecated.getStore('language').setCurrentId(languageId);
            this.getList();
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

            return this.currencyRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },


        getCurrencyColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-currency.list.columnName',
                routerLink: 'sw.settings.currency.detail',
                width: '250px',
                primary: true
            }, {
                property: 'shortName',
                inlineEdit: 'string',
                label: 'sw-settings-currency.list.columnShortName'
            }, {
                property: 'symbol',
                inlineEdit: 'string',
                label: 'sw-settings-currency.list.columnSymbol'
            }, {
                property: 'factor',
                inlineEdit: 'string',
                label: 'sw-settings-currency.list.columnFactor'
            }];
        }
    }
});
