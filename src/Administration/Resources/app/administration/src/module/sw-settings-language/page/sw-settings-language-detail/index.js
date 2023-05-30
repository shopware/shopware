/**
 * @package system-settings
 */
import template from './sw-settings-language-detail.html.twig';
import './sw-settings-language-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'feature',
    ],

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
            parentTranslationCodeId: null,
            translationCodeError: null,
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

        ...mapPropertyErrors('language', ['localeId', 'name']),
    },

    watch: {
        languageId() {
            // We must reset the page if the user clicks his browsers back button and navigates back to create
            if (this.languageId === null) {
                this.createdComponent();
            }
        },
        'language.translationCodeId'() {
            this.translationCodeError = null;
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

                if (language.parentId) {
                    this.setParentTranslationCodeId(language.parentId);
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('language').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        checkTranslationCodeInheritance(value) {
            return value === this.parentTranslationCodeId;
        },

        setParentTranslationCodeId(parentId) {
            this.languageRepository.get(parentId, Shopware.Context.api).then((parentLanguage) => {
                this.parentTranslationCodeId = parentLanguage.translationCodeId;
            });
        },

        onInputLanguage(parentId) {
            this.translationCodeError = null;

            if (parentId) {
                this.setParentTranslationCodeId(parentId);
            }

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

            if (!this.language.parentId && !this.language.translationCodeId) {
                this.translationCodeError = {
                    detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                };
            }

            this.languageRepository.save(this.language).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (!this.languageId) {
                    this.$router.push({ name: 'sw.settings.language.detail', params: { id: this.language.id } });
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.language.index' });
        },
    },
};
