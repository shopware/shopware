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

    methods: {
        createdComponent() {
            this.$super.createdComponent();

            this.repository.on('start.loading', () => {
                this.loading = true;
            });

            this.repository.on('finish.loading', (result) => {
                this.loading = false;
                this.applyResult(result);
            });
        },

        applyResult(result) {
            this.records = result;
            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
        },

        deleteItem(id) {
            this.deleteId = null;

            return this.repository.delete(id, this.records.context).then(() => {
                return this.repository.search(this.records.criteria, this.records.context);
            });
        },

        save(record) {
            return this.repository.save(record, this.records.context).then(() => {
                return this.repository.search(this.records.criteria, this.records.context);
            });
        },

        revert() {
            return this.repository.search(this.records.criteria, this.records.context);
        },

        sort(column) {
            this.records.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === column.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            this.records.criteria.addSorting(
                Criteria.sort(column.dataIndex, direction)
            );

            this.currentSortBy = column.dataIndex;
            this.currentSortDirection = direction;

            return this.repository.search(this.records.criteria, this.records.context);
        },

        paginate(params) {
            this.records.criteria.setPage(params.page);
            this.records.criteria.setLimit(params.limit);

            return this.repository.search(this.records.criteria, this.records.context);
        },

        showDelete(id) {
            this.deleteId = id;
        },

        closeModal() {
            this.deleteId = null;
        }
    }
};
