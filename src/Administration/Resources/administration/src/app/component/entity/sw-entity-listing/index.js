import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-entity-listing.html.twig';

export default {
    name: 'sw-entity-listing',
    extendsFrom: 'sw-data-grid',
    template,

    props: {
        detailRoute: {
            type: String,
            required: false
        },
        repository: {
            required: true,
            type: Object
        },
        items: {
            type: Object,
            required: false,
            default: null
        },
        dataSource: {
            type: [Array, Object],
            required: false
        },
        showSettings: {
            type: Boolean,
            default: true,
            required: false
        },
        fullPage: {
            type: Boolean,
            default: true,
            required: false
        },
        allowInlineEdit: {
            type: Boolean,
            default: true,
            required: false
        },
        allowColumnEdit: {
            type: Boolean,
            default: true,
            required: false
        }
    },

    data() {
        return {
            deleteId: null,
            page: 1,
            limit: 25,
            total: 10
        };
    },

    watch: {
        items() {
            this.applyResult(this.items);
        }
    },

    methods: {
        createdComponent() {
            this.$super.createdComponent();

            if (this.items) {
                this.applyResult(this.items);
            }
        },

        applyResult(result) {
            this.records = result.items;
            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
            this.loading = false;
        },

        deleteItem(id) {
            this.deleteId = null;

            // send delete request to the server, immediately
            return this.repository.delete(id, this.items.context).then(() => {
                return this.doSearch();
            });
        },

        doSearch() {
            this.loading = true;
            return this.repository.search(this.items.criteria, this.items.context).then(this.applyResult);
        },

        save(record) {
            // send save request to the server, immediately
            return this.repository.save(record, this.items.context).then(() => {
                return this.doSearch();
            });
        },

        revert() {
            // reloads the grid to revert all changes
            return this.doSearch();
        },

        sort(column) {
            this.items.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === column.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            this.items.criteria.addSorting(
                Criteria.sort(column.dataIndex, direction)
            );

            this.currentSortBy = column.dataIndex;
            this.currentSortDirection = direction;

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
};
