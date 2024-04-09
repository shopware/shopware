/**
 * @package buyers-experience
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
            usedTranslationIds: [],
            showAlertForChangeParentLanguage: false,
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
            parentTranslationCodeId: null,
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
            return (new Criteria(1, null))
                .addFilter(Criteria.not(
                    'and',
                    [Criteria.equals('id', this.languageId)],
                ))
                .addAggregation(
                    Criteria.terms('usedTranslationIds', 'language.translationCode.id', null, null, null),
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.languageId) {
                Shopware.State.commit('context/resetLanguageToDefault');
                this.language = this.languageRepository.create();

                return;
            }

            this.loadEntityData().then(() => {
                return this.loadCustomFieldSets();
            }).then(() => {
                this.languageRepository.search(this.usedLocaleCriteria).then((data) => {
                    this.usedTranslationIds = data.aggregations.usedTranslationIds.buckets.map((item) => item.key);
                });
            });
        },

        loadEntityData() {
            this.isLoading = true;
            return this.languageRepository.get(this.languageId).then((language) => {
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
            return this.customFieldDataProviderService.getCustomFieldSets('language').then((sets) => {
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
            if (parentId) {
                this.setParentTranslationCodeId(parentId);
            }

            const origin = this.language.getOrigin();
            if (this.language.isNew() || !origin.parentId) {
                return;
            }

            this.showAlertForChangeParentLanguage = origin.parentId !== this.language.parentId;
        },

        isLocaleAlreadyUsed(itemId) {
            return this.usedTranslationIds.some((localeId) => {
                return itemId === localeId;
            });
        },

        onSave() {
            this.isLoading = true;

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
