/**
 * @sw-package fundamentals@discovery
 */
import { useSnackbar } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-language-list.html.twig';
import './sw-settings-language-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'translationService',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            languages: null,
            parentLanguages: null,
            translationMetadata: {},
            total: 0,
            isLoading: true,
            sortBy: 'active',
            sortDirection: 'DESC',
            // @deprecated tag:v6.8.0 - Will be removed without replacement.
            filterSidebarItem: null,
            showAddLanguageModal: false,
            updatingLocales: [],
            snippetSelection: {},
            builtInLocales: [],
            showDeleteModal: false,
            deleteCandidates: [],
            deleteTranslationFiles: true,
            isDeleting: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        snackbar() {
            return useSnackbar();
        },

        selectedUpdatableLocales() {
            return Object.values(this.snippetSelection)
                .map((language) => language.locale?.code)
                .filter((localeCode) => this.translationMetadata[localeCode]?.updateAvailable);
        },

        selectedLanguages() {
            return Object.values(this.snippetSelection);
        },

        bulkDeleteLanguages() {
            return this.selectedLanguages.filter((language) => !this.isDefault(language.id));
        },

        deleteCandidateInstalledLocales() {
            return this.deleteCandidates
                .map((language) => language.locale?.code)
                .filter((localeCode) => this.isLocaleInstalled(localeCode));
        },

        sortedDeleteCandidates() {
            return [...this.deleteCandidates].sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
        },

        updatableLocales() {
            return Object.values(this.translationMetadata)
                .filter((entry) => entry.updateAvailable)
                .map((entry) => entry.locale);
        },

        isUpdatingSnippets() {
            return this.updatingLocales.length > 0;
        },

        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('locale');
            criteria.addAssociation('translationCode');
            criteria.addAssociation('salesChannels');

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            }

            if (this.sortBy !== 'name') {
                criteria.addSorting(Criteria.sort('name', 'ASC'));
            }

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        getColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-language.list.columnName',
                    dataIndex: 'name',
                    inlineEdit: true,
                },
                {
                    property: 'parent',
                    label: 'sw-settings-language.list.columnInherit',
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'locale',
                    dataIndex: 'locale.id',
                    label: 'sw-settings-language.list.columnLocaleName',
                },
                {
                    property: 'translationCode.code',
                    label: 'sw-settings-language.list.columnIsoCode',
                },
                {
                    property: 'salesChannels',
                    label: 'sw-settings-language.list.columnSalesChannels',
                    sortable: false,
                },
                {
                    property: 'snippetStatus',
                    label: 'sw-settings-language.list.columnSnippetStatus',
                    sortable: false,
                },
                {
                    property: 'active',
                    dataIndex: 'active',
                    label: 'sw-settings-language.list.columnActive',
                    inlineEdit: 'boolean',
                    align: 'center',
                },
            ];
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

        cardTitle() {
            return `${this.$t('sw-settings-language.list.cardTitle')} (${this.total})`;
        },

        snippetStatusConfig() {
            return {
                updateAvailable: {
                    variant: 'info',
                    statusIndicator: true,
                    label: 'sw-settings-language.list.snippetStatus.updateAvailable',
                },
                updating: {
                    variant: 'info',
                    statusIndicator: true,
                    label: 'sw-settings-language.list.snippetStatus.updating',
                },
            };
        },
    },

    methods: {
        createdComponent() {
            this.loadTranslationMetadata();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        registerFilterSidebarItem(sidebarItem) {
            this.filterSidebarItem = sidebarItem;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        openFilterSidebar() {
            if (!this.filterSidebarItem?.openContent) {
                return;
            }

            this.filterSidebarItem.openContent();
        },

        onRefresh() {
            this.getList();
            this.loadTranslationMetadata();
        },

        getList() {
            this.isLoading = true;
            return this.languageRepository.search(this.listingCriteria).then((languageResult) => {
                this.total = languageResult.total || this.total;

                const parentCriteria = new Criteria(1, this.limit);
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

        async loadTranslationMetadata() {
            const [
                listResponse,
                metaResponse,
            ] = await Promise.all([
                this.translationService.getList().catch(() => null),
                this.translationService.getMeta().catch(() => null),
            ]);

            if (listResponse === null || metaResponse === null) {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.list.snippetStatusLoadError'),
                });
            }

            this.translationMetadata = (listResponse?.items ?? []).reduce((map, entry) => {
                map[entry.locale] = entry;
                return map;
            }, {});

            this.builtInLocales = metaResponse?.builtInLocales ?? this.builtInLocales;
        },

        salesChannelLabel(item) {
            const count = item.salesChannels?.length ?? 0;

            if (count === 0) {
                return '';
            }

            return this.$t('sw-settings-language.list.salesChannelCount', count);
        },

        localeLabel(item) {
            const localeCode = item.locale?.code;

            if (!localeCode) {
                return '';
            }

            const metadata = this.translationMetadata[localeCode];

            // Pseudo languages borrow a real locale code, so only their own name describes them
            if (metadata?.isPseudoLanguage) {
                return metadata.name;
            }

            return Shopware.Utils.format.localeName(localeCode);
        },

        getSnippetStatus(item) {
            const localeCode = item.locale?.code;

            // Built-in languages ship with Shopware and never receive snippet updates.
            if (this.builtInLocales.includes(localeCode)) {
                return null;
            }

            if (!this.translationMetadata[localeCode]?.updateAvailable) {
                return null;
            }

            if (this.updatingLocales.includes(localeCode)) {
                return 'updating';
            }

            return 'updateAvailable';
        },

        onAddLanguage() {
            this.showAddLanguageModal = true;
        },

        async onLanguageAdded(locale) {
            this.showAddLanguageModal = false;

            const criteria = new Criteria(1, 1);
            criteria.addAssociation('locale');
            criteria.addFilter(Criteria.equals('locale.code', locale));

            const language = (await this.languageRepository.search(criteria)).first();

            if (language) {
                this.$router.push({
                    name: 'sw.settings.language.detail',
                    params: { id: language.id },
                    query: { languageCreated: 'true' },
                });

                return;
            }

            await Promise.all([
                this.getList(),
                this.loadTranslationMetadata(),
            ]);
        },

        async onUpdateAllSnippets() {
            // Re-check the current server state first so languages removed in the meantime are not re-created.
            await this.loadTranslationMetadata();

            return this.runSnippetUpdate(this.updatableLocales);
        },

        async onUpdateSnippets(item) {
            const localeCode = item.locale?.code;

            if (!localeCode) {
                return;
            }

            this.updatingLocales.push(localeCode);

            try {
                await this.translationService.install({
                    locales: [localeCode],
                    activate: true,
                });
                this.createNotificationSuccess({
                    message: this.$t('sw-settings-language.list.updateSnippetsSuccess'),
                });
                await this.loadTranslationMetadata();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.list.updateSnippetsError'),
                });
            } finally {
                this.updatingLocales = this.updatingLocales.filter((code) => code !== localeCode);
            }
        },

        onSelectionChange(selection) {
            this.snippetSelection = selection;
        },

        buildSnippetProgressSnackbar(processed, total) {
            return {
                message: this.$t('sw-settings-language.list.updateSnippetsProgress', { processed, total }),
                variant: 'progress',
                progressPercentage: Math.round((processed / total) * 100),
                duration: 0,
            };
        },

        onUpdateSelectedSnippets() {
            return this.runSnippetUpdate(this.selectedUpdatableLocales);
        },

        async runSnippetUpdate(locales) {
            if (!locales.length) {
                return;
            }

            const total = locales.length;
            const failed = [];
            let processed = 0;

            this.updatingLocales.push(...locales);
            const snackbar = this.snackbar.addSnackbar(this.buildSnippetProgressSnackbar(processed, total));

            for (const locale of locales) {
                try {
                    await this.translationService.install({ locales: [locale], activate: true });
                } catch {
                    failed.push(locale);
                }

                processed += 1;
                Object.assign(snackbar, this.buildSnippetProgressSnackbar(processed, total));
            }

            Object.assign(snackbar, {
                uploadState: failed.length ? 'error' : 'success',
                successMessage: this.$t('sw-settings-language.list.updateSnippetsSuccess'),
                errorMessage: failed.length ? this.$t('sw-settings-language.list.updateSnippetsError') : undefined,
            });

            this.updatingLocales = this.updatingLocales.filter((localeCode) => !locales.includes(localeCode));
            this.snippetSelection = {};
            this.$refs.languageGrid?.resetSelection();
            await this.loadTranslationMetadata();
        },

        getParentName(item) {
            if (!item.parentId) {
                return '';
            }

            return this.parentLanguages?.get(item.parentId)?.name ?? '';
        },

        isDefault(languageId) {
            return Shopware.Context.api.systemLanguageId
                ? Shopware.Context.api.systemLanguageId.includes(languageId)
                : false;
        },

        tooltipDelete(languageId) {
            if (!this.acl.can('language.deleter') && !this.isDefault(languageId)) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('language.deleter'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },

        onInlineEditSave(promise) {
            promise.then(() => {
                this.invalidateLanguageCaches();
            });
        },

        invalidateLanguageCaches() {
            Shopware.Service('cacheService').invalidateCaches({
                cacheKey: [
                    'shared-data',
                    'active-languages',
                ],
            });
        },

        isLocaleInstalled(localeCode) {
            return Boolean(localeCode && this.translationMetadata[localeCode]?.lastUpdate);
        },

        openDeleteModal(languages) {
            this.deleteCandidates = languages;
            this.deleteTranslationFiles = true;
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.deleteCandidates = [];
        },

        async confirmDelete() {
            const ids = this.deleteCandidates.map((language) => language.id);

            if (ids.length === 0) {
                this.closeDeleteModal();

                return;
            }

            const locales = this.deleteTranslationFiles ? this.deleteCandidateInstalledLocales : [];

            this.isDeleting = true;

            try {
                await this.languageRepository.syncDeleted(ids, Shopware.Context.api);
                await this.removeTranslationFiles(locales);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-language.list.deleteError'),
                });
            } finally {
                this.isDeleting = false;
                this.showDeleteModal = false;
                this.deleteCandidates = [];
                this.snippetSelection = {};
                this.$refs.languageGrid?.resetSelection();

                this.invalidateLanguageCaches();
                await this.getList();
                await this.loadTranslationMetadata();
            }
        },

        async removeTranslationFiles(locales) {
            for (const localeCode of locales) {
                await this.translationService.deleteTranslation(localeCode).catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-language.list.deleteTranslationFilesError'),
                    });
                });
            }
        },
    },
};
