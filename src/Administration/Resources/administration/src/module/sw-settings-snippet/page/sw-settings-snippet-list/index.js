import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.less';

Component.register('sw-settings-snippet-list', {
    template,

    inject: ['snippetSetService', 'snippetService'],

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'snippets',
            snippetSets: {},
            grid: [],
            metaId: '',
            isCustomState: this.$route.query.isCustomState
            resetItems: [],
            hasResetableItems: true,
            modalResetSelection: []
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        snippetStore() {
            return State.getStore('snippet');
        },
        queryIdCount() {
            return this.queryIds.length;
        },
        metaName() {
            return this.snippetSets[0].name;
        }
    },

    methods: {
        getList() {
            this.initializeSnippetSet();
        },

        initializeSnippetSet() {
            if (!this.$route.query.ids || this.$route.query.ids.length <= 0) {
                this.$router.back();

                return;
            }

            this.isLoading = true;
            this.queryIds = this.$route.query.ids;
            const criteria = CriteriaFactory.equalsAny('id', this.queryIds);

            this.snippetSetService.getCustomList(this.page, this.limit, this.term, this.isCustomState).then((response) => {
                this.snippetSetStore.getList({ criteria }).then((sets) => {
                    this.snippetSets = sets.items;
                    this.metaId = this.queryIds[0];
                    this.total = response.total;

                    this.grid = this.prepareGrid(response.data);
                }).then(() => {
                    this.isLoading = false;
                });
            });
        },

        prepareGrid(grid) {
            const result = [];
            Object.values(grid).forEach((items) => {
                const content = {};
                items.forEach((item) => {
                    content[item.setId] = item;
                });
                content.id = items[0].translationKey;
                result.push(content);
            });

            return result;
        },

        onEdit(snippet) {
            if (snippet && snippet.id) {
                this.$router.push({
                    name: 'sw.settings.snippet.detail',
                    params: {
                        id: snippet.id
                    }
                });
            }
        },

        onInlineEditSave(result) {
            const responses = [];
            const key = result[this.metaId].translationKey;

            this.snippetSets.forEach((item) => {
                if (result[item.id].value === null) {
                    result[item.id].value = result[item.id].origin;
                }

                if (result[item.id].origin !== result[item.id].value) {
                    responses.push(this.snippetService.save(result[item.id]));
                } else if (result[item.id].id !== null) {
                    responses.push(this.snippetService.delete(result[item.id].id));
                }
            });

            Promise.all(responses).then(() => {
                this.inlineSaveSuccessMessage(key);
                this.getList();
            }).catch(() => {
                this.inlineSaveErrorMessage(key);
                this.getList();
            });
        },

        onEmptyClick() {
            this.isCustomState = false;
            this.getList();
        },

        onChangeCustomItems(customItemState) {
            this.isCustomState = customItemState === true;
            this.getList();
        },

        inlineSaveSuccessMessage(key) {
            const titleSaveSuccess = this.$tc('sw-settings-snippet.list.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-snippet.list.messageSaveSuccess',
                this.queryIdCount,
                { key }
            );

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },

        inlineSaveErrorMessage(key) {
            const titleSaveError = this.$tc('sw-settings-snippet.list.titleSaveError');
            const messageSaveError = this.$tc(
                'sw-settings-snippet.list.messageSaveError',
                this.queryIdCount,
                { key }
            );

            this.createNotificationError({
                title: titleSaveError,
                message: messageSaveError
            });
        },

        onReset(item) {
            const keys = Object.keys(item);
            let i = keys.length;
            while (i) {
                i -= 1;
                if (keys[i] === 'id') {
                    keys.splice(i, 1);
                }
            }

            const criteria = CriteriaFactory.equalsAny('id', keys);
            this.isLoading = true;

            this.snippetSetStore.getList({ criteria }).then((response) => {
                const resetItems = [];
                Object.values(item).forEach((currentItem, index) => {
                    if (!(currentItem instanceof Object)) {
                        return;
                    }

                    currentItem.setName = this.getName(response.items, currentItem.setId);
                    if (currentItem.id === null) {
                        currentItem.id = index;
                        currentItem.isFileSnippet = true;
                    }

                    resetItems.push(currentItem);
                });

                this.resetItems = resetItems;
                this.showDeleteModal = item;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getName(list, id) {
            let name = '';
            list.forEach((item) => {
                if (item.id === id) {
                    name = item.name;
                }
            });

            return name;
        },

        onModalSelectionChanged(selection) {
            this.selection = selection;
            this.selectionCount = Object.keys(selection).length;
            this.hasResetableItems = this.selectionCount === 0;
        },

        onConfirmReset() {
            const items = Object.values(this.selection);
            this.showDeleteModal = false;

            items.forEach((item) => {
                if (item.hasOwnProperty('isFileSnippet')) {
                    return;
                }
                this.isLoading = true;
                this.snippetService.delete(item.id).then(() => {
                    this.createSuccessMessage(item);
                }).catch(() => {
                    this.createResetErrorNote(item);
                }).finally(() => {
                    this.isLoading = false;
                    this.getList();
                });
            });
        },

        createSuccessMessage(item) {
            const title = this.$tc('sw-settings-snippet.list.titleDeleteSuccess');
            const message = this.$tc(
                'sw-settings-snippet.list.resetSuccessMessage',
                0,
                {
                    key: item.value,
                    value: item.origin
                }
            );

            this.createNotificationSuccess({
                title,
                message
            });
        },

        createResetErrorNote(item) {
            const title = this.$tc('sw-settings-snippet.list.titleSaveError');
            const message = this.$tc(
                'sw-settings-snippet.list.resetErrorMessage',
                0,
                { key: item.value }
            );

            this.createNotificationError({
                title,
                message
            });
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.initializeSnippetSet();
        },

        onInlineEditCancel(rowItems) {
            Object.keys(rowItems).forEach((itemKey) => {
                const item = rowItems[itemKey];

                item.value = item.resetTo;
            });
        }
    }
});
