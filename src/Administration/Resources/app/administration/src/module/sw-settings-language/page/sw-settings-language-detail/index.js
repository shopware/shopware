/**
 * @sw-package fundamentals@discovery
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
        'translationService',
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
            showAllSalesChannels: false,
            justCreated: false,
            snippetMetadata: null,
            builtInLocales: [],
            isUpdatingSnippets: false,
            isSnippetMetadataLoading: false,
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
            return this.language && typeof this.language.isNew === 'function' ? this.language.isNew() : false;
        },

        usedLocaleCriteria() {
            return new Criteria(1, null)
                .addFilter(
                    Criteria.not('and', [
                        Criteria.equals('id', this.languageId),
                    ]),
                )
                .addAggregation(Criteria.terms('usedTranslationIds', 'language.translationCode.id', null, null, null));
        },

        allowSave() {
            return this.isNewLanguage ? this.acl.can('language.creator') : this.acl.can('language.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
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
                return this.$t('sw-settings-language.detail.tooltipInheritanceNotPossible');
            }

            return this.$t('sw-settings-language.detail.tooltipLanguageNotChoosable');
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },

        assignedSalesChannels() {
            return this.language?.salesChannels ?? [];
        },

        visibleSalesChannels() {
            if (this.showAllSalesChannels) {
                return this.assignedSalesChannels;
            }

            return Array.from(this.assignedSalesChannels).slice(0, 3);
        },

        snippetUpdateState() {
            if (!this.language) {
                return null;
            }

            const localeCode = this.language.locale?.code;

            if (this.builtInLocales.includes(localeCode)) {
                return 'builtIn';
            }

            if (!this.snippetMetadata) {
                return 'notAvailable';
            }

            const isLinked = this.snippetMetadata.lastUpdate !== null;

            if (this.isUpdatingSnippets) {
                return isLinked ? 'updating' : 'linking';
            }

            if (!isLinked) {
                return 'notLinked';
            }

            return this.snippetMetadata.updateAvailable ? 'updateAvailable' : 'upToDate';
        },

        snippetUpdatesLabel() {
            return (
                {
                    builtIn: 'sw-settings-language.detail.snippetUpdates.builtIn',
                    notAvailable: 'sw-settings-language.detail.snippetUpdates.notAvailable',
                    notLinked: 'sw-settings-language.detail.snippetUpdates.notLinked',
                    linking: 'sw-settings-language.detail.snippetUpdates.linking',
                    updating: 'sw-settings-language.detail.snippetUpdates.updating',
                    updateAvailable: 'sw-settings-language.detail.snippetUpdates.updateAvailable',
                    upToDate: 'sw-settings-language.detail.snippetUpdates.upToDate',
                }[this.snippetUpdateState] ?? 'sw-settings-language.detail.snippetUpdates.upToDate'
            );
        },

        showSnippetUpdateButton() {
            return [
                'notLinked',
                'linking',
                'updateAvailable',
                'updating',
            ].includes(this.snippetUpdateState);
        },

        snippetUpdateButtonLabel() {
            return (
                {
                    notLinked: 'sw-settings-language.detail.snippetUpdates.linkButton',
                    linking: 'sw-settings-language.detail.snippetUpdates.linkingButton',
                    updateAvailable: 'sw-settings-language.detail.snippetUpdates.updateButton',
                    updating: 'sw-settings-language.detail.snippetUpdates.updatingButton',
                }[this.snippetUpdateState] ?? 'sw-settings-language.detail.snippetUpdates.updateButton'
            );
        },

        showSnippetAutoUpdate() {
            return [
                'upToDate',
                'updateAvailable',
                'updating',
            ].includes(this.snippetUpdateState);
        },

        salesChannelsEmptyHint() {
            return this.language?.active
                ? 'sw-settings-language.detail.salesChannels.assignHint'
                : 'sw-settings-language.detail.salesChannels.activateHint';
        },

        salesChannelsCardTitle() {
            const title = this.$t('sw-settings-language.detail.salesChannels.title');

            return this.assignedSalesChannels.length ? `${title} (${this.assignedSalesChannels.length})` : title;
        },

        ...mapPropertyErrors('language', [
            'localeId',
            'name',
        ]),
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

    updated() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route?.query?.languageCreated) {
                this.justCreated = true;
                this.$router.replace({
                    name: 'sw.settings.language.detail',
                    params: { id: this.languageId },
                    query: {},
                });
            }

            if (!this.languageId) {
                Shopware.Store.get('context').resetLanguageToDefault();
                this.language = this.languageRepository.create();
                this.language.active = true;

                return;
            }

            this.loadEntityData()
                .then(() => {
                    return this.loadCustomFieldSets();
                })
                .then(() => {
                    this.languageRepository.search(this.usedLocaleCriteria).then((data) => {
                        this.usedTranslationIds = data.aggregations.usedTranslationIds.buckets.map((item) => item.key);
                    });
                });
        },

        loadEntityData() {
            this.isLoading = true;

            const criteria = new Criteria(1, 1);
            criteria.addAssociation('locale');

            const salesChannelCriteria = criteria.getAssociation('salesChannels');
            salesChannelCriteria.addAssociation('type');
            salesChannelCriteria.addSorting(Criteria.sort('name', 'ASC'));

            return this.languageRepository
                .get(this.languageId, Shopware.Context.api, criteria)
                .then((language) => {
                    this.isLoading = false;
                    this.language = language;

                    if (language.parentId) {
                        this.setParentTranslationCodeId(language.parentId);
                    }

                    this.loadSnippetMetadata();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$t('sw-settings-language.detail.messageLoadError'),
                    });
                });
        },

        loadSnippetMetadata() {
            const localeCode = this.language?.locale?.code;

            if (!localeCode) {
                this.snippetMetadata = null;

                return Promise.resolve();
            }

            this.isSnippetMetadataLoading = true;

            return Promise.all([
                this.translationService.getList(),
                this.translationService.getMeta(),
            ])
                .then(
                    ([
                        listResponse,
                        metaResponse,
                    ]) => {
                        this.builtInLocales = metaResponse?.builtInLocales ?? this.builtInLocales;
                        this.snippetMetadata =
                            (listResponse?.items ?? []).find((item) => item.locale === localeCode) ?? null;
                    },
                )
                .catch(() => {
                    this.snippetMetadata = null;
                    this.createNotificationError({
                        message: this.$t('sw-settings-language.detail.snippetUpdates.statusLoadError'),
                    });
                })
                .finally(() => {
                    this.isSnippetMetadataLoading = false;
                });
        },

        onUpdateSnippets() {
            const localeCode = this.language?.locale?.code;

            if (!localeCode) {
                return;
            }

            this.isUpdatingSnippets = true;

            this.translationService
                .install({ locales: [localeCode], activate: true })
                .then(() => this.loadSnippetMetadata())
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-language.detail.snippetUpdates.updateError'),
                    });
                })
                .finally(() => {
                    this.isUpdatingSnippets = false;
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

            this.languageRepository
                .save(this.language)
                .then(() => {
                    this.invalidateLanguageCaches();
                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    if (!this.languageId) {
                        this.$router.push({
                            name: 'sw.settings.language.detail',
                            params: { id: this.language.id },
                            query: { languageCreated: 'true' },
                        });
                    }
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$t('sw-settings-language.detail.messageSaveError'),
                    });
                });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.language.index' });
        },

        onOpenSalesChannelSettings() {
            this.$router.push({ name: 'sw.sales.channel.list' });
        },

        invalidateLanguageCaches() {
            Shopware.Service('cacheService').invalidateCaches({
                cacheKey: [
                    'shared-data',
                    'active-languages',
                ],
            });
        },
    },
};
