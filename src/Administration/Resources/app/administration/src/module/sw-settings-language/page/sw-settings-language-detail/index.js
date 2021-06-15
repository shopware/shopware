import template from './sw-settings-language-detail.html.twig';
import './sw-settings-language-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-language-detail', {
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        languageId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            language: null,
            usedLocales: [],
            showAlertForChangeParentLanguage: false,
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.languageHasName ? this.language.name : '';
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        isIsoCodeRequired() {
            return !this.language.parentId;
        },

        languageHasName() {
            return this.language !== null && this.language.name;
        },

        isNewLanguage() {
            return this.language && typeof this.language.isNew === 'function'
                ? this.language.isNew()
                : false;
        },

        usedLocaleCriteria() {
            return (new Criteria(1, 1)).addAggregation(
                Criteria.terms('usedLocales', 'language.locale.code', null, null, null),
            );
        },

        allowSave() {
            return this.isNewLanguage
                ? this.acl.can('language.creator')
                : this.acl.can('language.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        parentLanguageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.language.id)]));
            return criteria;
        },

        isSystemDefaultLanguageId() {
            return this.language.id === Shopware.Context.api.systemLanguageId;
        },

        inheritanceTooltipText() {
            if (this.isSystemDefaultLanguageId) {
                return this.$tc('sw-settings-language.detail.tooltipInheritanceNotPossible');
            }

            return this.$tc('sw-settings-language.detail.tooltipLanguageNotChoosable');
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        languageId() {
            // We must reset the page if the user clicks his browsers back button and navigates back to create
            if (this.languageId === null) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.languageId) {
                Shopware.State.commit('context/resetLanguageToDefault');
                this.language = this.languageRepository.create();
            } else {
                this.loadEntityData();
                this.loadCustomFieldSets();
            }

            this.languageRepository.search(this.usedLocaleCriteria).then(({ aggregations }) => {
                this.usedLocales = aggregations.usedLocales.buckets;
            });
        },

        loadEntityData() {
            this.isLoading = true;
            this.languageRepository.get(this.languageId).then((language) => {
                this.isLoading = false;
                this.language = language;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('language').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onInputLanguage() {
            const origin = this.language.getOrigin();
            if (this.language.isNew() || !origin.parentId) {
                return;
            }

            this.showAlertForChangeParentLanguage = origin.parentId !== this.language.parentId;
        },

        isLocaleAlreadyUsed(item) {
            const usedByAnotherLanguage = this.usedLocales.some((locale) => {
                return item.code === locale.key;
            });

            if (usedByAnotherLanguage) {
                return true;
            }

            if (!this.language.locale) {
                return false;
            }

            return item.code === this.language.locale.code;
        },

        onSave() {
            this.isLoading = true;
            this.languageRepository.save(this.language).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (!this.languageId) {
                    this.$router.push({ name: 'sw.settings.language.detail', params: { id: this.language.id } });
                }
            }).then(() => {
                this.loadEntityData();
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.language.index' });
        },

        onChangeLanguage() {
            this.loadEntityData();
        },
    },
});
