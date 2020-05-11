import template from './sw-entity-listing.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const bulkActionOptions = {
    delete: "delete",
    activate: "activate",
    deactivate: "deactivate",
    assignRemoveCategories: "assignRemoveCategories",
}

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
        }
    },

    data() {
        return {
            selectedCategories: null,
            deleteId: null,
            showBulkDeleteModal: false,
            showBulkActivateModal: false,
            showBulkDeactivateModal: false,
            showBulkAssignRemoveCategoriesModal: false,
            isBulkLoading: false,
            page: 1,
            limit: this.criteriaLimit,
            total: 10,
            selectOptions: [
                {
                    value: bulkActionOptions.delete,
                    label: "Delete"
                },
                {
                    value: bulkActionOptions.activate,
                    label: "Activate"
                },
                {
                    value: bulkActionOptions.deactivate,
                    label: "Deactivate"
                },
                {
                    value: bulkActionOptions.assignRemoveCategories,
                    label: "Assign / Remove categories"
                },
            ]
        };
    },

    watch: {
        items() {
            this.applyResult(this.items);
        }
    },

    methods: {
        bulkAction(event) {
            switch (event) {
                case bulkActionOptions.delete:
                    this.showBulkDeleteModal = true;
                    break;
                case bulkActionOptions.activate:
                    this.showBulkActivateModal = true;
                    break;
                case bulkActionOptions.deactivate:
                    this.showBulkDeactivateModal = true;
                    break;
                case bulkActionOptions.assignRemoveCategories:
                    this.showBulkAssignRemoveCategoriesModal = true;
                    break;
            }
        },

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

        deactivateItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                selectedProxy.active = false;
                promises.push(this.repository.save(
                    selectedProxy,
                    this.items.context)
                );
            });

            return Promise.all(promises).then(() => {
                return this.bulkDeactivateActionFinish();
            }).catch(() => {
                return this.bulkDeactivateActionFinish();
            });
        },

        bulkDeactivateActionFinish() {
            this.showBulkDeactivateModal = false;
            this.$emit('items-deactivate-finish');
            return this.bulkActionFinish();
        },

        activateItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                selectedProxy.active = true;
                promises.push(this.repository.save(
                    selectedProxy,
                    this.items.context)
                );
            });

            return Promise.all(promises).then(() => {
                return this.bulkActivateActionFinish();
            }).catch(() => {
                return this.bulkActivateActionFinish();
            });
        },

        bulkActivateActionFinish() {
            this.showBulkActivateModal = false;
            this.$emit('items-activate-finish');
            return this.bulkActionFinish();
        },

        assignRemoveCategoriesItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                selectedProxy.active = true;
                promises.push(this.repository.save(
                    selectedProxy,
                    this.items.context)
                );
            });

            return Promise.all(promises).then(() => {
                return this.bulkAssignRemoveCategoriesActionFinish();
            }).catch(() => {
                return this.bulkAssignRemoveCategoriesActionFinish();
            });
        },

        bulkAssignRemoveCategoriesActionFinish() {
            this.showBulkAssignRemoveCategoriesModal = false;
            // this.$emit('items-activate-finish');
            return this.bulkActionFinish();
        },

        deleteItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                promises.push(this.repository.delete(selectedProxy.id, this.items.context));
            });

            return Promise.all(promises).then(() => {
                return this.bulkDeleteActionFinish();
            }).catch(() => {
                return this.bulkDeleteActionFinish();
            });
        },

        bulkDeleteActionFinish() {
            this.showBulkDeleteModal = false;
            this.$emit('items-delete-finish');
            return this.bulkActionFinish();
        },

        bulkActionFinish() {
            this.resetSelection();
            this.isBulkLoading = false;

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
            this.items.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === column.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            column.dataIndex.split(',').forEach((field) => {
                this.items.criteria.addSorting(
                    Criteria.sort(field, direction, column.naturalSorting)
                );
            });

            this.currentSortBy = column.dataIndex;
            this.currentSortDirection = direction;
            this.currentNaturalSorting = column.naturalSorting;
            this.$emit('column-sort', column);

            return this.doSearch();
        },

        paginate({ page = 1, limit = 25 }) {
            this.items.criteria.setPage(page);
            this.items.criteria.setLimit(limit);

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
