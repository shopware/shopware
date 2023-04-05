/**
 * @package admin
 */

import template from './sw-entity-listing.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.extend('sw-entity-listing', 'sw-data-grid', {
    template,

    inject: ['feature'],

    props: {
        detailRoute: {
            type: String,
            required: false,
            default: null,
        },

        repository: {
            type: Object,
            required: true,
        },

        items: {
            type: Array,
            required: false,
            default: null,
        },

        // FIXME: add default value to this property
        // eslint-disable-next-line vue/require-default-prop
        dataSource: {
            type: [Array, Object],
            required: false,
        },

        showSettings: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        steps: {
            type: Array,
            required: false,
            default() {
                return [10, 25, 50, 75, 100];
            },
        },

        fullPage: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowInlineEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowColumnEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        criteriaLimit: {
            type: Number,
            required: false,
            default: 25,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowView: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        disableDataFetching: {
            type: Boolean,
            required: false,
            default: false,
        },

        naturalSorting: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowBulkEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        showBulkEditModal: {
            type: Boolean,
            required: false,
            default: false,
        },

        bulkGridEditColumns: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        maximumSelectItems: {
            type: Number,
            required: false,
            default: 1000,
        },
    },

    data() {
        return {
            deleteId: null,
            showBulkDeleteModal: false,
            isBulkLoading: false,
            page: 1,
            limit: this.criteriaLimit,
            total: 10,
            lastSortedColumn: null,
        };
    },
    computed: {
        detailPageLinkText() {
            if (!this.allowEdit && this.allowView) {
                return this.$tc('global.default.view');
            }

            return this.$tc('global.default.edit');
        },
    },

    watch: {
        items() {
            this.applyResult(this.items);
        },
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
            this.page = result.criteria.page || 1;
            this.limit = result.criteria.limit || this.criteriaLimit;
            this.loading = false;

            this.$emit('update-records', result);
        },

        deleteItem(id) {
            this.deleteId = null;

            // send delete request to the server, immediately
            return this.repository.delete(id, this.items.context).then(() => {
                this.resetSelection();
                this.$emit('delete-item-finish', id);
                return this.doSearch();
            }).catch((errorResponse) => {
                this.$emit('delete-item-failed', { id, errorResponse });
            });
        },

        deleteItems() {
            this.isBulkLoading = true;

            let selectedIds = null;

            selectedIds = Object.keys(this.selection);

            return this.repository.syncDeleted(selectedIds, this.items.context).then(() => {
                return this.deleteItemsFinish();
            }).catch((errorResponse) => {
                this.$emit('delete-items-failed', { selectedIds, errorResponse });
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
                    Criteria.sort(field, direction, this.lastSortedColumn.naturalSorting),
                );
            });

            this.currentSortBy = this.lastSortedColumn.dataIndex;
            this.currentSortDirection = direction;
            this.currentNaturalSorting = this.lastSortedColumn.naturalSorting;

            this.$emit('column-sort', this.lastSortedColumn, this.currentSortDirection);

            if (this.lastSortedColumn.useCustomSort) {
                return false;
            }

            if (this.disableDataFetching) {
                return false;
            }

            return this.doSearch();
        },

        paginate({ page = 1, limit = 25 }) {
            this.items.criteria.setPage(page);
            this.items.criteria.setLimit(limit);

            this.$emit('page-change', { page, limit });

            if (this.lastSortedColumn && this.lastSortedColumn.useCustomSort) {
                return false;
            }

            if (this.disableDataFetching) {
                return false;
            }

            return this.doSearch();
        },

        showDelete(id) {
            this.deleteId = id;
        },

        closeModal() {
            this.deleteId = null;
        },

        onClickBulkEdit() {
            this.$emit('bulk-edit-modal-open');
        },

        onCloseBulkEditModal() {
            this.$emit('bulk-edit-modal-close');
        },
    },
});
