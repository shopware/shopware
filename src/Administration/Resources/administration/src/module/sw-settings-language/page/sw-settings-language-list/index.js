import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-language-list.html.twig';
import './sw-settings-language-list.scss';

Component.register('sw-settings-language-list', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            languages: null,
            parentLanguages: null,
            filterRootLanguages: false,
            filterInheritedLanguages: false,
            isLoading: true
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'DESC'));
            }

            if (this.filterRootLanguages) {
                criteria.addFilter(Criteria.equals('parentId', null));
            }

            if (this.filterInheritedLanguages) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('parentId', null)]));
            }

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        getColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-settings-language.list.columnName'),
                inlineEdit: true
            }, {
                property: 'locale',
                label: this.$tc('sw-settings-language.list.columnLocaleName')
            }, {
                property: 'translationCode.code',
                label: this.$tc('sw-settings-language.list.columnIsoCode')
            }, {
                property: 'parent',
                label: this.$tc('sw-settings-language.list.columnInherit')
            }, {
                property: 'id',
                label: this.$tc('sw-settings-language.list.columnDefault')
            }];
        }
    },

    watch: {
        listingCriteria() {
            this.getList();
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            return this.languageRepository.search(this.listingCriteria, this.context).then((languageResult) => {
                const parentCriteria = (new Criteria(1, this.limit));
                const parentIds = {};

                languageResult.forEach((language) => {
                    if (language.parentId) {
                        parentIds[language.parentId] = true;
                    }
                });

                parentCriteria.setIds(Object.keys(parentIds));
                return this.languageRepository.search(parentCriteria, this.context).then((parentResult) => {
                    this.languages = languageResult;
                    this.parentLanguages = parentResult;
                    this.isLoading = false;
                });
            });
        },

        getParentName(item) {
            if (item.parentId === null) {
                return '-';
            }

            return this.parentLanguages.get(item.parentId).name;
        },

        onChangeLanguage() {
            this.getList();
        },

        isDefault(languageId) {
            return this.context.defaultLanguageIds.includes(languageId);
        }
    }
});
