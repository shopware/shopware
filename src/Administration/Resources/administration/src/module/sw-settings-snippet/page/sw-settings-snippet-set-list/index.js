import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.less';

Component.register('sw-settings-snippet-set-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('sw-settings-list')
    ],

    inject: ['snippetSetService'],

    data() {
        return {
            isLoading: false,
            snippetSets: [],
            offset: 0,
            showDeleteModal: false,
            showCloneModal: false
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

        createCloneErrorNote() {
            this.createNotificationError({
                title: this.$tc('sw-settings-snippet.list.cloneNoteTitle'),
                message: this.$tc('sw-settings-snippet.list.errorMessage')
            });
        },

        createCloneSuccessNote() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.list.cloneNoteTitle'),
                message: this.$tc('sw-settings-snippet.list.cloneSuccessMessage')
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
