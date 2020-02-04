import template from './sw-product-cross-selling-assignment.html.twig';
import './sw-product-cross-selling-assignment.scss';

const {debounce, get} = Shopware.Utils;
const {Criteria, EntityCollection} = Shopware.Data;

const {Component, Context, Mixin} = Shopware;

Component.register('sw-product-cross-selling-assignment', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('position')
    ],

    props: {
        assignedProducts: {
            type: Array,
            required: true
        },

        columns: {
            type: Array,
            required: true
        },

        localMode: {
            type: Boolean,
            required: true
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 10
        },

        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, this.resultLimit);
            }
        },

        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        },

        crossSellingId: {
            type: String,
            required: true
        },

        placeholder: {
            type: String,
            required: false,
            default() {
                return this.$tc('global.entity-components.placeholderToManyAssociationCard');
            }
        },

        searchableFields: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            gridCriteria: null,
            searchCriteria: null,
            isLoadingResults: false,
            isLoadingGrid: false,
            selectedIds: [],
            total: 0,
            resultCollection: null,
            gridData: this.assignedProducts,
            positionColumnKey: 0,
            searchTerm: '',
            totalAssigned: 0,
            loadingGridState: false
        };
    },

    computed: {
        crossSellingAssigmentRepository() {
            return this.repositoryFactory.create('product_cross_selling_assigned_products');
        },

        context() {
            return this.assignedProducts.context;
        },

        languageId() {
            return this.context.languageId;
        },

        assignmentRepository() {
            return this.repositoryFactory.create(
                this.assignedProducts.entity,
                this.assignedProducts.source
            );
        },

        searchRepository() {
            return this.repositoryFactory.create(
                'product'
            );
        },

        page: {
            get() {
                return this.gridCriteria.page;
            },
            set(page) {
                this.gridCriteria.page = page;
            }
        },

        limit: {
            get() {
                return this.gridCriteria.limit;
            },
            set(limit) {
                this.gridCriteria.page = limit;
            }
        },

        focusEl() {
            return this.$refs.searchInput;
        },

        originalFilters() {
            return this.criteria.filters;
        }
    },

    watch: {
        criteria: {
            immediate: true,
            handler() {
                this.gridCriteria = Criteria.fromCriteria(this.criteria);
                this.searchCriteria = Criteria.fromCriteria(this.criteria);
            }
        },

        assignedProducts() {
            this.selectedIds = this.assignedProducts.map(product => product.productId);

            if (!this.localMode) {
                // this.paginateGrid();
                return;
            }

            this.gridData = this.assignedProducts;
        },

        languageId() {
            if (!this.localMode) {
                // this.paginateGrid();
            }
        },

        'selectedIds.length'() {
            this.total = this.selectedIds.length;
        }
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
            if (!this.localMode) {
                this.selectedIds = this.assignedProducts.map(product => product.productId);
                return;
            }
            this.gridData = this.assignedProducts;
        },

        onSearchTermChange(input) {
            this.searchTerm = input.target.value || null;

            this.debouncedSearch();
        },

        debouncedSearch: debounce(function debouncedSearch() {
            this.resetSearchCriteria();
            this.searchCriteria.term = this.searchTerm || null;

            this.addContainsFilter(this.searchCriteria);

            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        }, 500),

        onSelectExpanded() {
            this.resetSearchCriteria();

            this.focusEl.select();

            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        },

        paginateResult() {
            if (this.resultCollection.length >= this.resultCollection.total) {
                return;
            }

            this.searchCriteria.page += 1;

            this.searchItems().then((searchResult) => {
                this.resultCollection.push(...searchResult);
            });
        },

        searchItems() {
            return this.searchRepository.search(this.searchCriteria, this.context).then((result) => {
                if (!this.localMode) {
                    const criteria = new Criteria(1, this.searchCriteria.limit);
                    criteria.setIds(result.getIds());

                    this.assignmentRepository.searchIds(criteria, this.context).then(({data}) => {
                        data.forEach((id) => {
                            if (!this.isSelected({id})) {
                                this.selectedIds.push(id);
                            }
                        });
                    });
                }

                return result;
            });
        },

        onItemSelect(item) {
            if (this.isSelected(item)) {
                this.removeItem(item);
                return;
            }

            if (this.localMode) {
                const newCollection = EntityCollection.fromCollection(this.gridData);
                newCollection.push(item);

                this.selectedIds = newCollection.getIds();
                this.gridData = newCollection;

                this.$emit('change', newCollection);
                return;
            }

            const entity = this.assignmentRepository.create(this.context);

            this.getMaximumPosition().then((maximumPosition) => {
                entity.crossSellingId = this.crossSellingId;
                entity.productId = item.id;
                entity.position = maximumPosition;

                this.assignmentRepository.save(entity, this.context).then(() => {
                    this.selectedIds.push(item.id);
                });
            });
        },

        removeItem(item) {
            const productId = item.productId ? item.productId : item.id;
            const itemCriteria = new Criteria();
            itemCriteria.addPostFilter(Criteria.equals('productId', productId));

            return this.assignmentRepository.search(itemCriteria, this.context).then((result) => {
                const assigmentIds = result.getIds();

                return this.assignmentRepository.delete(assigmentIds[0], this.context).then(() => {
                    this.selectedIds = this.selectedIds.filter((selectedId) => {
                        return selectedId !== productId;
                    });
                });
            });
        },

        isSelected(item) {
            return this.selectedIds.some((selectedId) => {
                return item.id === selectedId;
            });
        },

        resetActiveItem() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },

        onSelectCollapsed() {
            this.resultCollection = null;
            this.focusEl.blur();

            if (!this.localMode) {
                // this.paginateGrid();
            }
        },

        onPositionChanged() {
            this.$refs.optionGrid.load();
        },

        resetSearchCriteria() {
            this.searchCriteria.page = 1;
            this.searchCriteria.term = this.searchTerm || null;
            this.searchCriteria.limit = this.resultLimit;

            this.addContainsFilter(this.searchCriteria);
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        paginateGrid({page, limit} = this.gridCriteria) {
            this.gridCriteria.page = page;
            this.gridCriteria.limit = limit;
            this.setGridFilter();

            this.isLoadingGrid = true;
            this.assignmentRepository.search(this.gridCriteria, this.context).then((assignments) => {
                this.gridData = assignments;
                this.isLoadingGrid = false;
            });
        },

        setGridFilter() {
            this.gridCriteria.term = this.searchTerm || null;
            this.addContainsFilter(this.gridCriteria);
        },

        addContainsFilter(criteria) {
            if (criteria.term === null) {
                criteria.filters = [...this.originalFilters];
                return;
            }

            if (this.searchableFields.length > 0) {
                const containsFilter = this.searchableFields.map((field) => {
                    return Criteria.contains(field, criteria.term);
                });

                criteria.filters = [
                    ...this.criteria.filters,
                    Criteria.multi(
                        'OR',
                        containsFilter
                    )
                ];
                criteria.term = null;
            }
        },

        removeFromGrid(item) {
            this.removeItem(item).then(() => {
                this.resultCollection = null;
                if (!this.localMode) {
                    // this.paginateGrid();
                }
            });
        },

        getMaximumPosition() {
            return this.getNewPosition(this.assignmentRepository, this.criteria, Context.api);
        },

        forceRerender() {
            this.positionColumnKey += 1;
        }
    }
});
