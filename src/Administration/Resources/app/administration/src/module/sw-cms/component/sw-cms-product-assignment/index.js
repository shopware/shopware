import template from './sw-cms-product-assignment.html.twig';
import './sw-cms-product-assignment.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('sw-cms-product-assignment', 'sw-many-to-many-assignment-card', {
    template,
    data() {
        return {
            steps: [5],
        };
    },

    watch: {
        criteria: {
            immediate: true,
            handler() {
                this.gridCriteria = Criteria.fromCriteria(this.criteria);
                this.searchCriteria = Criteria.fromCriteria(this.criteria);

                this.paginateGrid();
            },
        },

        entityCollection() {
            this.selectedIds = this.entityCollection.getIds();

            this.paginateGrid();
        },

        languageId() {
            this.paginateGrid();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initData();
        },

        initData() {
            this.page = 1;
            this.selectedIds = this.entityCollection.getIds();
        },

        searchItems() {
            return this.searchRepository.search(this.searchCriteria, this.context).then((result) => {
                const criteria = new Criteria(1, this.searchCriteria.limit);
                criteria.setIds(result.getIds());

                return result;
            });
        },

        onItemSelect(item) {
            if (this.isSelected(item)) {
                this.removeItem(item);
                return;
            }

            this.entityCollection.add(item);

            this.selectedIds = this.entityCollection.getIds();
            this.gridData = this.entityCollection;

            this.$emit('change', this.entityCollection);
        },

        removeItem(item) {
            this.entityCollection.remove(item.id);

            this.selectedIds = this.entityCollection.getIds();
            this.gridData = this.entityCollection;
            this.$emit('change', this.entityCollection);

            return Promise.resolve();
        },


        onSelectCollapsed() {
            this.resultCollection = null;
            this.focusEl.blur();

            this.paginateGrid();
        },

        paginateGrid({ page, limit } = this.gridCriteria) {
            this.gridCriteria.page = page;
            this.gridCriteria.limit = limit;
            this.isLoadingGrid = true;
            const currentPaginateCollection = this.entityCollection.slice((page - 1) * limit, (page - 1) * limit + limit);
            this.gridData = currentPaginateCollection;
            this.isLoadingGrid = false;
            this.$emit('paginate', this.gridData);
        },

        removeFromGrid(item) {
            this.removeItem(item).then(() => {
                this.paginateGrid();
            });
        },
    },
});
