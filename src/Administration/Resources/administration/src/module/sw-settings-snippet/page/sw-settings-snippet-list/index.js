import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.scss';

Component.register('sw-settings-snippet-list', {
    template,

    inject: ['snippetSetService', 'snippetService', 'userService'],

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'snippets',
            metaId: '',
            currentAuthor: '',
            snippetSets: {},
            hasResetableItems: true,
            isCustomState: false,
            emptySnippets: false,
            grid: [],
            resetItems: [],
            filterItems: [],
            authorFilters: [],
            appliedFilter: [],
            appliedAuthors: [],
            emptyIcon: this.$route.meta.$module.icon
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
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
                this.backRoutingError();
                this.$router.back();

                return;
            }

            this.userService.getUser().then((response) => {
                this.currentAuthor = `user/${response.data.name}`;
            });

            this.isLoading = true;
            this.queryIds = this.$route.query.ids;
            const criteria = CriteriaFactory.equalsAny('id', this.queryIds);

            this.snippetService.getFilter().then((response) => {
                this.filterItems = response.data;
            });

            this.snippetSetService.getAuthors().then((response) => {
                this.authorFilters = response.data;
            });

            const filter = {
                custom: this.isCustomState,
                empty: this.emptySnippets,
                term: this.term,
                namespace: this.appliedFilter,
                author: this.appliedAuthors,
                translationKey: []
            };

            const sort = {
                sortBy: this.sortBy,
                sortDirection: this.sortDirection
            };

            this.snippetSetService.getCustomList(this.page, this.limit, filter, sort).then((response) => {
                this.snippetSetStore.getList({ sortBy: 'name', sortDirection: 'ASC', criteria }).then((sets) => {
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
            function prepareContent(items) {
                const content = items.reduce((acc, item) => {
                    acc[item.setId] = item;
                    return acc;
                }, {});
                content.id = items[0].translationKey;
                return content;
            }

            return Object.values(grid).reduce((accumulator, items) => {
                accumulator.push(prepareContent(items));
                return accumulator;
            }, []);
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
                const snippet = result[item.id];

                if (snippet.value === '') {
                    snippet.value = snippet.origin;
                }

                if (!snippet.hasOwnProperty('author') || snippet.author === '') {
                    snippet.author = this.currentAuthor;
                }

                if (snippet.origin !== snippet.value) {
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.id !== null) {
                    responses.push(this.snippetService.delete(snippet.id));
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


        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.initializeSnippetSet();
        },

        onInlineEditCancel(rowItems) {
            Object.keys(rowItems).forEach((itemKey) => {
                const item = rowItems[itemKey];
                if (typeof item !== 'object' || item.value === undefined) {
                    return;
                }

                item.value = item.resetTo;
            });
        },

        backRoutingError() {
            this.createNotificationSuccess({
                title: this.$tc('sw-settings-snippet.general.errorBackRoutingTitle'),
                message: this.$tc('sw-settings-snippet.general.errorBackRoutingMessage')
            });
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
            const ids = this.$route.query.ids;
            const criteria = CriteriaFactory.equalsAny('id', ids);
            this.isLoading = true;

            this.snippetSetStore.getList({ criteria }).then((response) => {
                const resetItems = [];
                Object.values(item).forEach((currentItem, index) => {
                    if (!(currentItem instanceof Object) || !ids.find(id => id === currentItem.setId)) {
                        return;
                    }

                    currentItem.setName = this.getName(response.items, currentItem.setId);
                    if (currentItem.id === null) {
                        currentItem.id = index;
                        currentItem.isFileSnippet = true;
                    }

                    resetItems.push(currentItem);
                });

                this.resetItems = resetItems.sort((a, b) => {
                    return a.setName <= b.setName ? -1 : 1;
                });
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

        onSelectionChanged(selection) {
            this.selection = selection;
            this.selectionCount = Object.keys(selection).length;
            this.hasResetableItems = this.selectionCount === 0;
        },

        onConfirmReset(fullSelection) {
            let items;
            if (this.isCustomState) {
                items = Object.values(fullSelection).filter(item => typeof item !== 'string');
            } else if (this.selection !== undefined) {
                items = Object.values(this.selection);
            } else {
                items = Object.values(this.resetItems);
            }

            this.showDeleteModal = false;

            items.forEach((item) => {
                if (item.hasOwnProperty('isFileSnippet') || item.id === null) {
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

        onChange(field) {
            this.page = 1;
            if (field.group === 'customSnippets') {
                this.isCustomState = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.group === 'emptySnippets') {
                this.emptySnippets = field.value;
                this.initializeSnippetSet();
                return;
            }

            let selector = 'appliedFilter';
            if (field.group === 'authorFilter') {
                selector = 'appliedAuthors';
            }

            if (field.value) {
                if (this[selector].indexOf(field.name) !== -1) {
                    return;
                }

                this[selector].push(field.name);
                this.initializeSnippetSet();
                return;
            }

            this[selector].splice(this[selector].indexOf(field.name), 1);
            this.initializeSnippetSet();
        },

        onSidebarClose() {
            this.isCustomState = false;
            this.emptySnippets = false;
            this.appliedAuthors = [];
            this.appliedFilter = [];
            this.initializeSnippetSet();
        },

        onSortColumn(column) {
            if (column.dataIndex !== this.sortBy) {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
                this.getList();
                return;
            }

            if (this.sortDirection === 'ASC') {
                this.sortDirection = 'DESC';
            } else {
                this.sortDirection = 'ASC';
            }

            this.getList();
        }
    }
});
