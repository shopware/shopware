import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.scss';

Component.register('sw-settings-snippet-set-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    inject: ['snippetSetService'],

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
            snippetsEditiable: false,
            selection: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            return this.loadBaseFiles().then(() => {
                return this.snippetSetStore.getList(params).then((response) => {
                    this.total = response.total;
                    this.snippetSets = response.items;
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
            const response = new Promise((resolve, reject) => {
                const snippetSet = this.snippetSetStore.create();
                snippetSet.baseFile = Object.values(this.baseFiles)[0].name;

                const result = this.snippetSets.splice(0, 0, snippetSet);

                if (result.length === 0) {
                    resolve(snippetSet);
                } else {
                    reject();
                }
            });

            response.then((snippetSet) => {
                this.$nextTick(() => {
                    const foundRow = this.$refs.snippetSetList.$children.find((vueComponent) => {
                        return vueComponent.item !== undefined && vueComponent.item.id === snippetSet.id;
                    });

                    if (!foundRow) {
                        return false;
                    }
                    foundRow.isEditingActive = true;

                    return true;
                });
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;
            if (this.baseFiles[item.baseFile].iso !== null) {
                item.iso = this.baseFiles[item.baseFile].iso;

                item.save().then(() => {
                    this.isLoading = false;
                    this.createInlineSuccessNote(item.name);
                }).catch(() => {
                    this.isLoading = false;
                    this.createInlineErrorNote(item.name);
                    this.getList();
                });
            } else {
                this.isLoading = false;
                this.createInlineErrorNote(item.name);
                this.getList();
            }
        },

        onEditSnippetSets() {
            if (!this.snippetsEditiable) {
                this.createNotEditableErrorNote();

                return;
            }
            const selection = Object.keys(this.selection);

            this.$router.push({
                name: 'sw.settings.snippet.list',
                query: { ids: selection }
            });
        },

        onSelectionChanged(selection) {
            this.selection = selection;
            this.selectionCount = Object.keys(selection).length;
            this.snippetsEditiable = this.selectionCount >= 1;
        },

        onInlineEditCancel() {
            this.getList();
        },

        onDeleteSet(id) {
            this.showDeleteModal = id;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.snippetSetStore.getById(id).delete(true).then(() => {
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

        onConfirmClone(id) {
            this.isLoading = true;
            this.snippetSetService.clone(id).then((clone) => {
                this.snippetSetStore.getByIdAsync(clone.id).then((set) => {
                    if (!set) {
                        return;
                    }

                    set.name = `${set.name} ${this.$tc('sw-settings-snippet.general.copyName')}`;

                    new Promise((resolve) => {
                        const baseName = set.name;
                        const checkUsedNames = item => item.name === set.name;
                        let copyCounter = 1;

                        while (this.snippetSets.some(checkUsedNames)) {
                            copyCounter += 1;
                            set.name = `${baseName} (${copyCounter})`;
                        }
                        resolve();
                    }).then(() => {
                        set.save().then(() => {
                            this.createCloneSuccessNote();
                            this.getList();
                        }).catch(() => {
                            set.delete().then(() => {
                                this.createCloneErrorNote();
                                this.getList();
                            });
                        });
                    });
                });
                this.isLoading = false;
                this.closeCloneModal();
            }).catch(() => {
                this.isLoading = false;
                this.closeCloneModal();
                this.createCloneErrorNote();
            });
        },

        createDeleteSuccessNote() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.setList.deleteNoteSuccessTitle'),
                message: this.$tc('sw-settings-snippet.setList.deleteNoteSuccessMessage')
            });
        },

        createDeleteErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.deleteNoteErrorTitle'),
                message: this.$tc('sw-settings-snippet.setList.deleteNoteErrorMessage')
            });
        },

        createInlineSuccessNote(name) {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.setList.inlineEditSuccessTitle'),
                message: this.$tc('sw-settings-snippet.setList.inlineEditSuccessMessage', 0, { name })
            });
        },

        createInlineErrorNote(name) {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.inlineEditErrorTitle'),
                message: this.$tc('sw-settings-snippet.setList.inlineEditErrorMessage', name !== null, { name })
            });
        },

        createCloneSuccessNote() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.setList.cloneNoteSuccessTitle'),
                message: this.$tc('sw-settings-snippet.setList.cloneSuccessMessage')
            });
        },

        createCloneErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.cloneNoteErrorTitle'),
                message: this.$tc('sw-settings-snippet.setList.cloneErrorMessage')
            });
        },

        createNotEditableErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.notEditableNoteErrorTitle'),
                message: this.$tc('sw-settings-snippet.setList.notEditableNoteErrorMessage')
            });
        }
    }
});
