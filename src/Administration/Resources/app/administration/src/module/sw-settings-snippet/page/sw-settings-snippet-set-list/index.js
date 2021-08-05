import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('sw-settings-snippet-set-list', {
    template,

    inject: [
        'snippetSetService',
        'repositoryFactory',
        'acl',
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
                this.baseFiles = response.items;
            });
        },

        onAddSnippetSet() {
            const newSnippetSet = this.snippetSetRepository.create();
            newSnippetSet.baseFile = Object.values(this.baseFiles)[0].name;

            const result = this.snippetSets.splice(0, 0, newSnippetSet);

            if (result.length !== 0) {
                return;
            }

            this.$nextTick(() => {
                const foundRow = this.$refs.snippetSetList.$children.find((vueComponent) => {
                    return vueComponent.item !== undefined && vueComponent.item.id === newSnippetSet.id;
                });

                if (!foundRow) {
                    return false;
                }

                foundRow.isEditingActive = true;

                return true;
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            const match = Object.values(this.baseFiles).find((element) => {
                return element.name === item.baseFile;
            });

            if (match && match.iso !== null) {
                item.iso = match.iso;

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
});
