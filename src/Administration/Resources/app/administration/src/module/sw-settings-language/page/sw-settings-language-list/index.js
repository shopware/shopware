import template from './sw-settings-language-list.html.twig';
import './sw-settings-language-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-language-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            languages: null,
            parentLanguages: null,
            total: 0,
            filterRootLanguages: false,
            filterInheritedLanguages: false,
            isLoading: true,
            sortBy: 'name',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('locale');

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
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
                inlineEdit: true,
            }, {
                property: 'locale',
                dataIndex: 'locale.id',
                label: 'sw-settings-language.list.columnLocaleName',
            }, {
                property: 'locale.code',
                label: 'sw-settings-language.list.columnIsoCode',
            }, {
                property: 'parent',
                dataIndex: 'parent.id',
                label: 'sw-settings-language.list.columnInherit',
            }, {
                property: 'id',
                label: 'sw-settings-language.list.columnDefault',
            }];
        },

        allowCreate() {
            return this.acl.can('language.creator');
        },

        allowView() {
            return this.acl.can('language.viewer');
        },

        allowEdit() {
            return this.acl.can('language.editor');
        },

        allowInlineEdit() {
            return this.acl.can('language.editor');
        },

        allowDelete() {
            return this.acl.can('language.deleter');
        },
    },

    methods: {
        getList() {
            this.isLoading = true;
            return this.languageRepository.search(this.listingCriteria).then((languageResult) => {
                this.total = languageResult.total || this.total;

                const parentCriteria = (new Criteria(1, this.limit));
                const parentIds = {};

                languageResult.forEach((language) => {
                    if (language.parentId) {
                        parentIds[language.parentId] = true;
                    }
                });

                parentCriteria.setIds(Object.keys(parentIds));
                return this.languageRepository.search(parentCriteria).then((parentResult) => {
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
            return Shopware.Context.api.systemLanguageId
                ? Shopware.Context.api.systemLanguageId.includes(languageId)
                : false;
        },

        tooltipDelete(languageId) {
            if (!this.acl.can('language.deleter') && !this.isDefault(languageId)) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('language.deleter'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },
    },
});
