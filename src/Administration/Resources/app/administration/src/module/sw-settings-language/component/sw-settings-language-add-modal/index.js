/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-language-add-modal.html.twig';
import './sw-settings-language-add-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'translationService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    emits: [
        'close',
        'language-added',
    ],

    data() {
        return {
            translations: [],
            documentationUrlSnippetKey: null,
            completenessThreshold: null,
            existingLanguageLocales: [],
            selectedLocale: null,
            isLoading: false,
            isSaving: false,
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageOptions() {
            return this.translations
                .map((translation) => {
                    const isLinked = translation.lastUpdate !== null;
                    const existsAsLanguage = this.existingLanguageLocales.includes(translation.locale);
                    const isPseudoLanguage = translation.isPseudoLanguage === true;

                    return {
                        value: translation.locale,
                        // Pseudo languages borrow a real locale code, so only their own name describes them
                        label: isPseudoLanguage ? translation.name : Shopware.Utils.format.localeName(translation.locale),
                        disabled: isLinked || existsAsLanguage,
                        isPseudoLanguage,
                    };
                })
                .sort((a, b) => {
                    if (a.isPseudoLanguage !== b.isPseudoLanguage) {
                        return a.isPseudoLanguage ? 1 : -1;
                    }

                    return a.label.localeCompare(b.label);
                });
        },

        selectedTranslation() {
            return this.translations.find((translation) => translation.locale === this.selectedLocale) ?? null;
        },

        translationsSufficient() {
            const progress = this.selectedTranslation?.progress;

            if (typeof progress !== 'number' || this.completenessThreshold === null) {
                return true;
            }

            return progress >= this.completenessThreshold;
        },

        translationsHintTextKey() {
            return this.translationsSufficient
                ? 'sw-settings-language.addModal.translationsAvailable'
                : 'sw-settings-language.addModal.translationsIncomplete';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            const [
                listResponse,
                metaResponse,
            ] = await Promise.all([
                this.translationService.getList().catch(() => null),
                this.translationService.getMeta().catch(() => null),
                this.loadExistingLanguageLocales(),
            ]);

            if (listResponse === null || metaResponse === null) {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.addModal.messageTranslationsLoadError'),
                });
            }

            this.translations = listResponse?.items ?? [];
            this.documentationUrlSnippetKey = metaResponse?.documentationUrlSnippetKey ?? null;
            this.completenessThreshold = metaResponse?.completenessThreshold ?? null;

            this.isLoading = false;
        },

        async loadExistingLanguageLocales() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('locale');

            const languages = await this.languageRepository.search(criteria).catch(() => {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.addModal.messageLanguagesLoadError'),
                });
                return [];
            });
            this.existingLanguageLocales = languages.map((language) => language.locale?.code).filter((code) => code);
        },

        async onAddLanguage() {
            if (!this.selectedLocale) {
                return;
            }

            this.isSaving = true;

            try {
                await this.translationService.install({
                    locales: [this.selectedLocale],
                    activate: true,
                });

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-language.addModal.messageAddSuccess'),
                });

                this.$emit('language-added', this.selectedLocale);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.addModal.messageAddError'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        onClose() {
            this.$emit('close');
        },
    },
};
