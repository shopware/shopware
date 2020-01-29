import CriteriaFactory from 'src/core/factory/criteria.factory';
import Sanitizer from 'src/core/helper/sanitizer.helper';
import template from './sw-settings-snippet-list.html.twig';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-settings-snippet-list', {
    template,

    inject: ['snippetSetService', 'snippetService', 'userService'],

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'snippets',
            sortBy: 'id',
            sortDirection: 'ASC',
            metaId: '',
            currentAuthor: '',
            snippetSets: null,
            hasResetableItems: true,
            showOnlyEdited: false,
            showOnlyAdded: false,
            emptySnippets: false,
            grid: [],
            resetItems: [],
            filterItems: [],
            authorFilters: [],
            appliedFilter: [],
            appliedAuthors: [],
            emptyIcon: this.$route.meta.$module.icon,
            skeletonItemAmount: 25
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.snippetSets ? this.$tc(
                'sw-settings-snippet.list.identifier',
                this.snippetSets.length,
                {
                    setName: this.metaName
                }
            ) : '';
        },

        columns() {
            return this.getColumns();
        },

        snippetSetStore() {
            return StateDeprecated.getStore('snippet_set');
        },

        queryIdCount() {
            return this.queryIds.length;
        },

        metaName() {
            return this.snippetSets[0].name;
        },

        filter() {
            const filter = {};
            if (this.showOnlyEdited) {
                filter.edited = true;
            }
            if (this.showOnlyAdded) {
                filter.added = true;
            }
            if (this.emptySnippets) {
                filter.empty = true;
            }
            if (this.term) {
                filter.term = this.term;
            }
            if (this.appliedFilter.length > 0) {
                filter.namespace = this.appliedFilter;
            }
            if (this.appliedAuthors.length > 0) {
                filter.author = this.appliedAuthors;
            }

            return filter;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.queryIds = Array.isArray(this.$route.query.ids) ? this.$route.query.ids : [this.$route.query.ids];
            const criteria = CriteriaFactory.equalsAny('id', this.queryIds);
            this.snippetSetStore.getList({ sortBy: 'name', sortDirection: 'ASC', criteria }).then((sets) => {
                this.snippetSets = sets.items;
            });

            this.userService.getUser().then((response) => {
                this.currentAuthor = `user/${response.data.username}`;
            });

            this.snippetService.getFilter().then((response) => {
                this.filterItems = response.data;
            });

            this.snippetSetService.getAuthors().then((response) => {
                this.authorFilters = response.data;
            });
        },

        getList() {
            this.initializeSnippetSet();
        },

        getColumns() {
            const columns = [{
                property: 'id',
                label: 'sw-settings-snippet.list.columnKey',
                inlineEdit: true,
                allowResize: true,
                rawData: true,
                primary: true
            }];

            if (this.snippetSets) {
                this.snippetSets.forEach((item) => {
                    columns.push({
                        property: item.id,
                        label: item.name,
                        allowResize: true,
                        inlineEdit: 'string',
                        rawData: true
                    });
                });
            }
            return columns;
        },

        initializeSnippetSet() {
            if (!this.$route.query.ids) {
                this.backRoutingError();
                return;
            }

            this.isLoading = true;

            const sort = {
                sortBy: this.sortBy,
                sortDirection: this.sortDirection
            };

            this.snippetSetService.getCustomList(this.page, this.limit, this.filter, sort).then((response) => {
                this.metaId = this.queryIds[0];
                this.total = response.total;

                this.grid = this.prepareGrid(response.data);
                this.isLoading = false;
            });
        },

        prepareGrid(grid) {
            function prepareContent(items) {
                const content = items.reduce((acc, item) => {
                    item.resetTo = item.value;
                    acc[item.setId] = item;
                    acc.isCustomSnippet = item.author.includes('user/');
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
                snippet.value = Sanitizer.sanitize(snippet.value);

                if (!snippet.value || snippet.value.length === 0) {
                    snippet.value = snippet.origin;
                }

                if (!snippet.hasOwnProperty('author') || snippet.author === '') {
                    snippet.author = this.currentAuthor;
                }

                if (snippet.origin !== snippet.value) {
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.id !== null && !snippet.author.startsWith('user/')) {
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

        onInlineEditCancel(rowItems) {
            Object.keys(rowItems).forEach((itemKey) => {
                const item = rowItems[itemKey];
                if (typeof item !== 'object' || item.value === undefined) {
                    return;
                }

                item.value = item.resetTo;
            });
        },

        onEmptyClick() {
            this.showOnlyEdited = false;
            this.getList();
        },


        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.initializeSnippetSet();
        },

        backRoutingError() {
            this.$router.push({ name: 'sw.settings.snippet.index' });

            this.createNotificationError({
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
            const ids = Array.isArray(this.$route.query.ids) ? this.$route.query.ids : [this.$route.query.ids];
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
                this.isLoading = false;
            }).catch(() => {
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
            this.snippetSelection = selection;
            this.selectionCount = Object.keys(selection).length;
            this.hasResetableItems = this.selectionCount === 0;
        },

        onConfirmReset(fullSelection) {
            let items;
            const promises = [];

            if (this.showOnlyEdited) {
                items = Object.values(fullSelection).filter(item => typeof item !== 'string');
            } else if (this.snippetSelection !== undefined) {
                items = Object.values(this.snippetSelection);
            } else {
                items = Object.values(this.resetItems);
            }

            this.showDeleteModal = false;

            this.$nextTick(() => {
                items.forEach((item) => {
                    if (item.hasOwnProperty('isFileSnippet') || item.id === null) {
                        return;
                    }
                    item.isCustomSnippet = fullSelection.isCustomSnippet;
                    this.isLoading = true;
                    promises.push(this.snippetService.delete(item.id).then(() => {
                        this.createSuccessMessage(item);
                    }).catch(() => {
                        this.createResetErrorNote(item);
                    }));
                });
                Promise.all(promises).then(() => {
                    this.isLoading = false;
                    this.getList();
                }).catch(() => {
                    this.isLoading = false;
                    this.getList();
                });
            });
        },

        createSuccessMessage(item) {
            const title = this.$tc('sw-settings-snippet.list.titleDeleteSuccess');
            const message = this.$tc(
                'sw-settings-snippet.list.resetSuccessMessage',
                !item.isCustomSnippet,
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
                item.isCustomSnippet ? 2 : 0,
                { key: item.value }
            );

            this.createNotificationError({
                title,
                message
            });
        },

        onChange(field) {
            this.page = 1;
            if (field.group === 'editedSnippets') {
                this.showOnlyEdited = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.group === 'addedSnippets') {
                this.showOnlyAdded = field.value;
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
            this.showOnlyEdited = false;
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
        },

        onPageChange(opts) {
            this.page = opts.page;
            this.limit = opts.limit;
            this.updateRoute({
                page: this.page
            }, {
                ids: this.$route.query.ids
            });
        }
    }
});
