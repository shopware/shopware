import template from './sw-entity-listing.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('sw-entity-listing', 'sw-data-grid', {
    template,

    props: {
        detailRoute: {
            type: String,
            required: false
        },

        repository: {
            type: Object,
            required: true
        },

        items: {
            type: Array,
            required: false,
            default: null
        },

        dataSource: {
            type: [Array, Object],
            required: false
        },

        showSettings: {
            type: Boolean,
            required: false,
            default: true
        },

        fullPage: {
            type: Boolean,
            required: false,
            default: true
        },

        allowInlineEdit: {
            type: Boolean,
            required: false,
            default: true
        },

        allowColumnEdit: {
            type: Boolean,
            required: false,
            default: true
        },

        criteriaLimit: {
            type: Number,
            required: false,
            default: 25
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        },

        allowView: {
            type: Boolean,
            required: false,
            default: false
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            deleteId: null,
            showBulkDeleteModal: false,
            isBulkLoading: false,
            page: 1,
            limit: this.criteriaLimit,
            total: 10,
            lastSortedColumn: null
        };
    },
    computed: {
        detailPageLinkText() {
            if (!this.allowEdit && this.allowView) {
                return this.$tc('global.default.view');
            }

            return this.$tc('global.default.edit');
        }
    },

    watch: {
        items() {
            this.applyResult(this.items);
        }
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            if (this.items) {
                this.applyResult(this.items);
            }
        },

        applyResult(result) {
            this.records = result;
            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
            this.loading = false;

            this.$emit('update-records', result);
        },

        deleteItem(id) {
            this.deleteId = null;

            // send delete request to the server, immediately
            return this.repository.delete(id, this.items.context).then(() => {
                this.resetSelection();
                return this.doSearch();
            }).catch((errorResponse) => {
                this.$emit('delete-item-failed', { id, errorResponse });
            });
        },

        deleteItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                promises.push(this.repository.delete(selectedProxy.id, this.items.context));
            });

            return Promise.all(promises).then(() => {
                return this.deleteItemsFinish();
            }).catch(() => {
                return this.deleteItemsFinish();
            });
        },

        deleteItemsFinish() {
            this.resetSelection();
            this.isBulkLoading = false;
            this.showBulkDeleteModal = false;
            this.$emit('items-delete-finish');

            return this.doSearch();
        },

        doSearch() {
            this.loading = true;
            return this.repository.search(this.items.criteria, this.items.context).then(this.applyResult);
        },

        save(record) {
            // send save request to the server, immediately
            const promise = this.repository.save(record, this.items.context).then(() => {
                return this.doSearch();
            });
            this.$emit('inline-edit-save', promise, record);

            return promise;
        },

        revert() {
            // reloads the grid to revert all changes
            const promise = this.doSearch();
            this.$emit('inline-edit-cancel', promise);

            return promise;
        },

        sort(column) {
            this.lastSortedColumn = column;
            this.items.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === this.lastSortedColumn.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            this.lastSortedColumn.dataIndex.split(',').forEach((field) => {
                this.items.criteria.addSorting(
                    Criteria.sort(field, direction, this.lastSortedColumn.naturalSorting)
                );
            });

            this.currentSortBy = this.lastSortedColumn.dataIndex;
            this.currentSortDirection = direction;
            this.currentNaturalSorting = this.lastSortedColumn.naturalSorting;

            this.$emit('column-sort', this.lastSortedColumn);

            if (this.lastSortedColumn.useCustomSort) {
                return false;
            }

            return this.doSearch();
        },

        paginate({ page = 1, limit = 25 }) {
            this.items.criteria.setPage(page);
            this.items.criteria.setLimit(limit);

            this.$emit('paginate', this.lastSortedColumn);

            if (this.lastSortedColumn && this.lastSortedColumn.useCustomSort) {
                return false;
            }

            return this.doSearch();
        },

        showDelete(id) {
            this.deleteId = id;
        },

        closeModal() {
            this.deleteId = null;
        }
    }
});
