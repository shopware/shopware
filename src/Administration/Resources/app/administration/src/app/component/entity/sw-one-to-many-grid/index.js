import template from './sw-one-to-many-grid.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('sw-one-to-many-grid', 'sw-data-grid', {
    template,

    inject: ['repositoryFactory'],

    props: {
        collection: {
            required: true,
            type: Array
        },
        localMode: {
            type: Boolean,
            default: true
        },
        dataSource: {
            type: [Array, Object],
            required: false
        }
    },

    data() {
        return {
            page: 1,
            limit: 25,
            total: 0
        };
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            // assign collection as records for the sw-data-grid
            this.applyResult(this.collection);

            // local mode means, the records are loaded with the parent record
            if (this.localMode) {
                return Promise.resolve();
            }

            // Create repository by collection sources
            // the collection contains the route for the entities /customer/{id}/addresses
            this.repository = this.repositoryFactory.create(
                // product_price
                this.collection.entity,

                // product/{id}/price-rules/
                this.collection.source
            );

            // records contains a pre loaded offset
            if (this.records.length > 0) {
                return Promise.resolve();
            }

            return this.load();
        },

        applyResult(result) {
            this.result = result;
            this.records = result;

            if (result.total) {
                this.total = result.total;
            } else {
                this.total = result.length;
            }

            this.page = result.criteria.page || this.page;
            this.limit = result.criteria.limit || this.limit;
        },

        save(record) {
            if (this.localMode) {
                // records will be saved with the root record
                return Promise.resolve();
            }

            return this.repository.save(record, this.result.context).then(() => {
                return this.load();
            });
        },

        revert() {
            if (this.localMode) {
                return Promise.resolve();
            }

            return this.load();
        },

        load() {
            return this.repository.search(this.result.criteria, this.result.context)
                .then(this.applyResult);
        },

        deleteItem(id) {
            if (this.localMode) {
                this.collection.remove(id);
                // records will be saved with the root record
                return Promise.resolve();
            }

            return this.repository.delete(id, this.result.context).then(() => {
                return this.load();
            }).catch((errorResponse) => {
                this.$emit('delete-item-failed', { id, errorResponse });
            });
        },

        sort(column) {
            if (this.localMode) {
                this.$emit('column-sort', column);

                return Promise.resolve();
            }

            this.result.criteria.resetSorting();

            let direction = 'ASC';
            if (this.currentSortBy === column.dataIndex) {
                if (this.currentSortDirection === direction) {
                    direction = 'DESC';
                }
            }

            this.result.criteria.addSorting(
                Criteria.sort(column.dataIndex, direction, !!column.naturalSorting)
            );

            this.currentSortBy = column.dataIndex;
            this.currentSortDirection = direction;
            this.currentNaturalSorting = !!column.naturalSorting;

            return this.load();
        },

        paginate(params) {
            if (this.localMode) {
                return Promise.resolve();
            }

            this.result.criteria.setPage(params.page);
            this.result.criteria.setLimit(params.limit);

            return this.load();
        }
    }
});
