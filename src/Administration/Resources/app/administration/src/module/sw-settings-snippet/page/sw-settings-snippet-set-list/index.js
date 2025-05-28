/**
 * @sw-package discovery
 */
import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
        };
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

        snippetSetCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        contextMenuEditSnippet() {
            return this.acl.can('snippet.editor') ? this.$t('global.default.edit') : this.$t('global.default.view');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        baseFileOptions() {
            return this.baseFiles.map((file, index) => {
                return {
                    id: index,
                    value: file.name,
                    label: file.name,
                };
            });
        },

        snippetSetColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-snippet.setList.columnName'),
                    inlineEdit: 'string',
                },
                {
                    property: 'iso',
                    label: this.$t('sw-settings-snippet.setList.columnIso'),
                    inlineEdit: 'string',
                },
                {
                    property: 'baseFile',
                    label: this.$t('sw-settings-snippet.setList.columnBaseFile'),
                    inlineEdit: 'string',
                },
                {
                    property: 'updatedAt',
                    label: this.$t('sw-settings-snippet.setList.columnChangedAt'),
                },
            ];
        },
    },

    methods: {
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
                this.baseFiles = Object.values(response.items ?? {});
                this.baseFiles.sort((a, b) => a.name.localeCompare(b.name));
            });
        },

        async onAddSnippetSet() {
            const newSnippetSet = this.snippetSetRepository.create();
            newSnippetSet.iso = this.baseFiles[0].iso;
            newSnippetSet.baseFile = this.baseFiles[0].name;

            newSnippetSet.name = this.$t('sw-settings-snippet.setList.newSnippetName');

            const baseName = newSnippetSet.name;
            let copyCounter = 1;

            while (this.snippetSets.some((item) => item.name === newSnippetSet.name)) {
                copyCounter += 1;
                newSnippetSet.name = `${baseName} (${copyCounter})`;
            }

            await this.snippetSetRepository.save(newSnippetSet);
            await this.getList();
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            const match = this.baseFiles.find((element) => {
                return element.name === item.baseFile;
            });

            if (match && match.iso !== null) {
                item.iso = match.iso;

                this.snippetSetRepository
                    .save(item)
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

        async onConfirmDelete() {
            try {
                await this.snippetSetRepository.delete(this.showDeleteModal);
                await this.getList();
                this.createDeleteSuccessNote();
            } catch (e) {
                this.createDeleteErrorNote();
            }

            this.closeDeleteModal();
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
        },

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
        onClone(id) {
            this.showCloneModal = id;
        },

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
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

                set.name = `${set.name} ${this.$t('sw-settings-snippet.general.copyName')}`;

                const baseName = set.name;
                let copyCounter = 1;

                while (this.snippetSets.some((item) => item.name === set.name)) {
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
            }
        },

        createDeleteSuccessNote() {
            this.createNotificationSuccess({
                message: this.$t('sw-settings-snippet.setList.deleteNoteSuccessMessage'),
            });
        },

        createDeleteErrorNote() {
            this.createNotificationError({
                message: this.$t('sw-settings-snippet.setList.deleteNoteErrorMessage'),
            });
        },

        createInlineSuccessNote(name) {
            this.createNotificationSuccess({
                message: this.$t('sw-settings-snippet.setList.inlineEditSuccessMessage', { name }, 0),
            });
        },

        createInlineErrorNote(name) {
            this.createNotificationError({
                message: this.$t('sw-settings-snippet.setList.inlineEditErrorMessage', { name }, name !== null),
            });
        },

        createCloneSuccessNote() {
            this.createNotificationSuccess({
                message: this.$t('sw-settings-snippet.setList.cloneSuccessMessage'),
            });
        },

        createCloneErrorNote() {
            this.createNotificationError({
                message: this.$t('sw-settings-snippet.setList.cloneErrorMessage'),
            });
        },

        createNotEditableErrorNote() {
            this.createNotificationError({
                message: this.$t('sw-settings-snippet.setList.notEditableNoteErrorMessage'),
            });
        },

        /** @deprecated tag:v6.8.0 - Will be removed without replacement */
        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
                message: this.$t('sw-privileges.tooltip.warning'),
            };
        },
    },
};
