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
            offset: 0,
            snippetSets: [],
            showDeleteModal: false,
            showCloneModal: false,
            selection: {},
            snippetsEditiable: false
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

            return this.snippetSetStore.getList(params).then((response) => {
                this.total = response.total;
                this.snippetSets = response.items;
                this.isLoading = false;
            });
        },

        onAddSnippetSet() {
            const snippetSet = this.snippetSetStore.create();
            snippetSet.baseFile = 'en_GB.json';
            snippetSet.iso = 'en_GB';
            this.snippetSets.splice(0, 0, snippetSet);

            const foundRow = this.$refs.snippetSetList.$children.find((item) => {
                return item.$options.name === 'sw-grid-row';
            });

            if (!foundRow) {
                return false;
            }

            foundRow.isEditingActive = true;

            return true;
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
            }).catch(this.onCloseDeleteModal());
        },

        onClone(id) {
            this.showCloneModal = id;
        },

        closeCloneModal() {
            this.showCloneModal = false;
        },

        onConfirmClone(id) {
            this.snippetSetService.cloneSnippetSet(id).then(() => {
                this.getList().then(() => {
                    const set = this.findSnippetSet(id);
                    if (!set) {
                        return;
                    }

                    set.name = `${set.name} ${this.$tc('sw-settings-snippet.general.copyName')}`;
                    set.save().then(() => {
                        this.createCloneSuccessNote();
                    }).catch(() => {
                        set.delete().then(() => {
                            this.createCloneErrorNote();
                            this.getList();
                        });
                    });
                });
            }).catch(() => {
                this.createCloneErrorNote();
            }).finally(() => {
                this.closeCloneModal();
            });
        },

        createNotEditableErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.notEditableNoteTitle'),
                message: this.$tc('sw-settings-snippet.setList.notEditableNoteMessage')
            });
        },

        createCloneErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.setList.cloneNoteTitle'),
                message: this.$tc('sw-settings-snippet.setList.cloneErrorMessage')
            });
        },

        createCloneSuccessNote() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.setList.cloneNoteTitle'),
                message: this.$tc('sw-settings-snippet.setList.cloneSuccessMessage')
            });
        },

        findSnippetSet(id) {
            return this.snippetSets.find((element) => {
                if (element.id === id) {
                    return element;
                }

                return false;
            });
        }
    }
});
