import template from './sw-settings-language-list.html.twig';
import './sw-settings-language-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-language-list', {
    template,

    inject: ['repositoryFactory'],

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
            isLoading: true,
            sortBy: this.$route.params.sortBy || 'name'
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
            criteria.addAssociation('locale');

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
                label: 'sw-settings-language.list.columnName',
                dataIndex: 'name',
                inlineEdit: true
            }, {
                property: 'locale',
                dataIndex: 'locale.id',
                label: 'sw-settings-language.list.columnLocaleName'
            }, {
                property: 'locale.code',
                label: 'sw-settings-language.list.columnIsoCode'
            }, {
                property: 'parent',
                dataIndex: 'parent.id',
                label: 'sw-settings-language.list.columnInherit'
            }, {
                property: 'id',
                label: 'sw-settings-language.list.columnDefault'
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
            return this.languageRepository.search(this.listingCriteria, Shopware.Context.api).then((languageResult) => {
                const parentCriteria = (new Criteria(1, this.limit));
                const parentIds = {};

                languageResult.forEach((language) => {
                    if (language.parentId) {
                        parentIds[language.parentId] = true;
                    }
                });

                parentCriteria.setIds(Object.keys(parentIds));
                return this.languageRepository.search(parentCriteria, Shopware.Context.api).then((parentResult) => {
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
            return Shopware.Context.api.systemLanguageId.includes(languageId);
        }
    }
});
