import template from './sw-settings-country-list.html.twig';
import './sw-settings-country-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-country-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            entityName: 'country',
            country: null,
            sortBy: 'country.name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        }
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.countryRepository.search(criteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.country = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(promise, country) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-country.detail.titleSaveSuccess'),
                    message: this.$tc('sw-settings-country.detail.messageSaveSuccess', 0, { name: country.name })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-settings-country.detail.titleSaveError'),
                    message: this.$tc('sw-settings-country.detail.messageSaveError')
                });
            });
        },

        onChangeLanguage(languageId) {
            Shopware.StateDeprecated.getStore('language').setCurrentId(languageId);
            this.getList();
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.countryRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },

        getCountryColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-country.list.columnName',
                routerLink: 'sw.settings.country.detail',
                primary: true
            }, {
                property: 'position',
                inlineEdit: 'number',
                label: 'sw-settings-country.list.columnPosition'
            }, {
                property: 'iso',
                inlineEdit: 'string',
                label: 'sw-settings-country.list.columnIso'
            }, {
                property: 'iso3',
                inlineEdit: 'string',
                label: 'sw-settings-country.list.columnIso3'
            }, {
                property: 'active',
                inlineEdit: 'string',
                label: 'sw-settings-country.list.columnActive'
            }];
        }
    }
});
