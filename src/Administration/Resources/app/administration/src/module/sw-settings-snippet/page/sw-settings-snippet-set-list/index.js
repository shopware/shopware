/**
 * @package buyers-experience
 */
import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'snippetSetService',
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('sw-settings-list'),
    ],

    data() {
        return {
            isLoading: false,
            entityName: 'snippetSet',
            sortBy: 'name',
            sortDirection: 'ASC',
            offset: 0,
            baseFiles: [],
            snippetSets: [],
            showDeleteModal: false,
            showCloneModal: false,
            snippetsEditable: false,
            selection: {},
            isSaveSuccessful: false,
            keepSnippetsUpToDate: false,
            directories: [],
            selectedDirectory: null,

            selectedLocales: [],
        };
    },
    created() {
        this.fetchDirectories();
        this.fetchSelectedDirectories();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        snippetSetRepository() {
            return this.repositoryFactory.create('snippet_set');
        },
        snippet_set_locale() {
            return this.repositoryFactory.create('snippet_set_locale');
        },

        snippetSetCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addSorting(
                Criteria.sort('name', 'ASC'),
            );

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        contextMenuEditSnippet() {
            return this.acl.can('snippet.editor') ?
                this.$tc('global.default.edit') :
                this.$tc('global.default.view');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {



        async languageExists(isoCode) {
            const languageRepository = this.repositoryFactory.create('language');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('localeId', await this.getLocaleIdByIso(isoCode)));

            const languages = await languageRepository.search(criteria);
            return languages.length > 0;
        },
        async createLanguage(isoCode) {
            if (await this.languageExists(isoCode)) {
                this.createNotificationInfo({
                    message: this.$tc('Language already exists', 0, { name: isoCode.toUpperCase() }),
                });
                return;
            }

            const languageRepository = this.repositoryFactory.create('language');
            const newLanguage = languageRepository.create();
            newLanguage.name = isoCode.toUpperCase();
            newLanguage.localeId = await this.getLocaleIdByIso(isoCode);
            newLanguage.translationCodeId = newLanguage.localeId;

            try {
                await languageRepository.save(newLanguage);
                this.createNotificationSuccess({
                    message: this.$tc('Erfolgreich', 0, { name: newLanguage.name }),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('Fehler', 0, { name: newLanguage.name }),
                });
            }
        },

        async getLocaleIdByIso(isoCode) {
            const localeRepository = this.repositoryFactory.create('locale');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('code', isoCode));

            const locales = await localeRepository.search(criteria);
            if (locales.length > 0) {
                return locales[0].id;
            }


            return 'default-locale-id';
        },

        async onAddSnippetSet() {
            const newSnippetSet = this.snippetSetRepository.create();
            newSnippetSet.baseFile = Object.values(this.baseFiles)[0].name;

            const result = this.snippetSets.splice(0, 0, newSnippetSet);

            if (result.length !== 0) {
                return;
            }

            this.$nextTick(() => {
                let foundRow = this.$refs.snippetSetList.$children.find((vueComponent) => {
                    if (vueComponent.$options.name === 'AsyncComponentWrapper') {
                        vueComponent = vueComponent?.$children[0];
                    }

                    return vueComponent?.item !== undefined && vueComponent.item.id === newSnippetSet.id;
                });

                if (!foundRow) {
                    return false;
                }

                if (foundRow.$options.name === 'AsyncComponentWrapper') {
                    foundRow = foundRow.$children[0];
                }

                foundRow.isEditingActive = true;

                return true;
            });

            const isoCode = newSnippetSet.baseFile.split('.')[1];
            await this.createLanguage(isoCode);
        },


        async fetchDirectories() {
            const owner = 'shopware';
            const repo = 'translations';
            const url = `https://api.github.com/repos/${owner}/${repo}/git/trees/3dc0e06f86e7dbe47e6685928cacd49462614963?ref=main`;
            try {
                const response = await fetch(url, {});
                const data = await response.json();
                this.directories = data.tree.map((item) => {
                    return {
                        value: item.path,
                        label: item.path,
                    };
                });
            } catch (error) {
                console.error('Error fetching directories:', error);
            }
        },

        async fetchSelectedDirectories() {
            const snippetSetLocaleRepository = this.repositoryFactory.create('snippet_set_locale');
            const localeRepository = this.repositoryFactory.create('locale');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', this.snippetSets[0].id));

            try {
                const snippetSetLocales = await snippetSetLocaleRepository.search(criteria);
                const localePromises = snippetSetLocales.map(async (snippetSetLocale) => {
                    const locale = await localeRepository.get(snippetSetLocale.localeId);
                    return locale.code;
                });

                this.selectedDirectory = await Promise.all(localePromises);
            } catch (error) {
                console.error('Error fetching selected directories:', error);
            }
        },

        async onSave() {
            this.isLoading = true;
            const snippetSetLocaleRepository = this.repositoryFactory.create('snippet_set_locale');
            const localeRepository = this.repositoryFactory.create('locale');

            try {
                const localePromises = this.selectedDirectory.map(async (isoCode) => {
                    const criteria = new Criteria();
                    criteria.addFilter(Criteria.equals('code', isoCode));
                    const locales = await localeRepository.search(criteria);
                    return locales.length > 0 ? locales[0].id : null;
                });

                const localeIds = await Promise.all(localePromises);

                const savePromises = localeIds.map(async (selectedLocaleId, index) => {
                    if (selectedLocaleId) {
                        const snippetSetLocale = snippetSetLocaleRepository.create();
                        snippetSetLocale.snippetSetId = this.snippetSets[0].id;
                        snippetSetLocale.localeId = selectedLocaleId;
                        try {
                            await snippetSetLocaleRepository.save(snippetSetLocale);
                            this.isSaveSuccessful = true;
                        } catch (error) {
                            console.error('Error saving snippet set locale:', error);
                        }
                    }
                });

                await Promise.all(savePromises);
            } catch (error) {
                console.error('Error processing selected directories:', error);
            } finally {
                this.isLoading = false;
            }
        },


        getList() {
            this.isLoading = true;

            return this.loadBaseFiles().then(() => {
                return this.snippetSetRepository.search(this.snippetSetCriteria).then((response) => {
                    this.total = response.total;
                    this.snippetSets = response;
                    this.isLoading = false;
                });
            });
        },

        loadBaseFiles() {
            return this.snippetSetService.getBaseFiles().then((response) => {
                this.baseFiles = response.items;
            });
        },


        async onInlineEditSave(item) {
            this.isLoading = true;

            const match = Object.values(this.baseFiles).find((element) => {
                return element.name === item.baseFile;
            });

            if (match && match.iso !== null) {
                item.iso = match.iso;

                const isoCode = item.baseFile.split('.')[1];
                await this.createLanguage(isoCode);

                this.snippetSetRepository.save(item)
                    .then(() => {
                        this.createInlineSuccessNote(item.name);
                    })
                    .catch(() => {
                        this.createInlineErrorNote(item.name);
                        this.getList();
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
            } else {
                this.isLoading = false;
                this.createInlineErrorNote(item.name);
                this.getList();
            }
        },

        onEditSnippetSets() {
            if (!this.snippetsEditable) {
                this.createNotEditableErrorNote();

                return;
            }
            const selection = Object.keys(this.snippetSelection);

            this.$router.push({
                name: 'sw.settings.snippet.list',
                query: { ids: selection },
            });
        },

        onSelectionChanged(selection) {
            this.snippetSelection = selection;
            this.snippetSelectionCount = Object.keys(selection).length;
            this.snippetsEditable = this.snippetSelectionCount >= 1;
        },

        onInlineEditCancel() {
            this.getList();
        },

        onDeleteSet(id) {
            this.showDeleteModal = id;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.snippetSetRepository.delete(id)
                .then(() => {
                    this.getList();
                    this.createDeleteSuccessNote();
                }).catch(() => {
                    this.onCloseDeleteModal();
                    this.createDeleteErrorNote();
                });
        },

        onClone(id) {
            this.showCloneModal = id;
        },

        closeCloneModal() {
            this.showCloneModal = false;
        },

        async onConfirmClone(id) {
            this.isLoading = true;

            try {
                const clone = await this.snippetSetRepository.clone(id);
                const set = await this.snippetSetRepository.get(clone.id);

                if (!set) {
                    return;
                }

                set.name = `${set.name} ${this.$tc('sw-settings-snippet.general.copyName')}`;

                const baseName = set.name;
                const checkUsedNames = item => item.name === set.name;
                let copyCounter = 1;

                while (this.snippetSets.some(checkUsedNames)) {
                    copyCounter += 1;
                    set.name = `${baseName} (${copyCounter})`;
                }

                try {
                    await this.snippetSetRepository.save(set);

                    this.createCloneSuccessNote();
                } catch {
                    await this.snippetSetRepository.delete(set.id);

                    this.createCloneErrorNote();
                } finally {
                    this.getList();
                }
            } catch {
                this.createCloneErrorNote();
            } finally {
                this.isLoading = false;
                this.closeCloneModal();
            }
        },

        createDeleteSuccessNote() {
            this.createNotificationSuccess({
                message: this.$tc('sw-settings-snippet.setList.deleteNoteSuccessMessage'),
            });
        },

        createDeleteErrorNote() {
            this.createNotificationError({
                message: this.$tc('sw-settings-snippet.setList.deleteNoteErrorMessage'),
            });
        },

        createInlineSuccessNote(name) {
            this.createNotificationSuccess({
                message: this.$tc('sw-settings-snippet.setList.inlineEditSuccessMessage', 0, { name }),
            });
        },

        createInlineErrorNote(name) {
            this.createNotificationError({
                message: this.$tc('sw-settings-snippet.setList.inlineEditErrorMessage', name !== null, { name }),
            });
        },

        createCloneSuccessNote() {
            this.createNotificationSuccess({
                message: this.$tc('sw-settings-snippet.setList.cloneSuccessMessage'),
            });
        },

        createCloneErrorNote() {
            this.createNotificationError({
                message: this.$tc('sw-settings-snippet.setList.cloneErrorMessage'),
            });
        },

        createNotEditableErrorNote() {
            this.createNotificationError({
                message: this.$tc('sw-settings-snippet.setList.notEditableNoteErrorMessage'),
            });
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
                message: this.$tc('sw-privileges.tooltip.warning'),
            };
        },
    },
};
