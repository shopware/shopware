import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-visibility-select.html.twig';

Component.extend('sw-product-visibility-select', 'sw-multi-select', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        options: {
            type: Array,
            required: false
        },
        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'id'
        },
        localMode: {
            type: Boolean,
            default: false
        },
        collection: {
            type: Array,
            required: true
        },
        resultLimit: {
            type: Number,
            required: false,
            default: 25
        }
    },

    data() {
        return {
            limit: this.valueLimit,
            repository: null,
            searchRepository: null,
            selectedIds: [],
            searchCriteria: null
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ])
    },

    methods: {
        initData() {
            if (!this.collection.entity) {
                return null;
            }

            this.repository = this.repositoryFactory.create(this.collection.entity, this.collection.source);

            this.searchRepository = this.repositoryFactory.create('sales_channel');

            this.collection.criteria.setLimit(this.valueLimit);
            this.searchCriteria = new Criteria(1, this.resultLimit);

            this.selectedIds = this.collection.getIds();

            this.$on('scroll', this.paginate);

            if (this.localMode) {
                return Promise.resolve();
            }

            this.displayAssigned(this.collection);

            return Promise.resolve();
        },

        reloadVisibleItems() {
            this.displayAssigned(this.collection);
            return Promise.resolve();
        },

        search() {
            this.searchCriteria.setTerm(this.searchTerm);
            this.searchCriteria.setPage(1);
            this.currentOptions = [];
            return this.sendSearchRequest();
        },

        isSelected(item) {
            const itemId = item.id;

            return this.visibleValues.some((value) => {
                return value.salesChannelId === itemId;
            });
        },

        getAssociation(itemId) {
            return this.visibleValues.find((value) => {
                return value.salesChannelId === itemId;
            });
        },

        openResultList(event) {
            if (this.isExpanded === false) {
                this.currentOptions = [];
                this.page = 1;

                this.$super.openResultList(event);

                return this.loadResultList();
            }

            return this.$super.openResultList(event);
        },

        loadResultList() {
            this.searchCriteria = new Criteria(1, this.resultLimit);
            return this.sendSearchRequest();
        },

        paginate(event) {
            if (this.getDistFromBottom(event.target) !== 0) {
                return Promise.resolve();
            }

            this.searchCriteria.setPage(this.searchCriteria.page + 1);
            return this.sendSearchRequest();
        },

        remove(identifier) {
            // remove identifier from visible element list
            this.visibleValues = this.visibleValues.filter((item) => {
                return item[this.valueProperty] !== identifier;
            });

            this.selectedIds = this.selectedIds.filter((id) => {
                return id !== identifier;
            });

            this.collection.remove(identifier);
            return Promise.resolve();
        },

        addItem({ item }) {
            // Remove when already selected
            if (this.isSelected(item)) {
                const removeValue = this.getAssociation(item.id);
                this.remove(removeValue.id);
                return Promise.resolve();
            }

            // Create new entity
            const newSalesChannelAssociation = this.repository.create(this.collection.context);
            newSalesChannelAssociation.productId = this.product.id;
            newSalesChannelAssociation.productVersionId = this.product.versionId;
            newSalesChannelAssociation.salesChannelId = item.id;
            newSalesChannelAssociation.visibility = 30;
            newSalesChannelAssociation.salesChannel = item;

            this.collection.add(newSalesChannelAssociation);
            this.reloadVisibleItems();

            return Promise.resolve();
        },

        sendSearchRequest() {
            return this.searchRepository.search(this.searchCriteria, this.context)
                .then((searchResult) => {
                    if (searchResult.length <= 0) {
                        return searchResult;
                    }

                    const criteria = new Criteria();
                    criteria.setIds(searchResult.getIds());

                    return this.repository.searchIds(criteria, this.collection.context).then((assigned) => {
                        assigned.data.forEach((id) => {
                            this.selectedIds.push(id);
                        });

                        this.displaySearch(searchResult);

                        return searchResult;
                    });
                });
        },

        getDistFromBottom(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        },

        displayAssigned(result) {
            this.selectedIds = [];
            this.visibleValues = [];
            result.forEach((item) => {
                this.selectedIds.push(item[this.valueProperty]);
                this.visibleValues.push(item);
            });

            this.invisibleValueCount = result.total - this.visibleValues.length;
        },

        displaySearch(result) {
            result.forEach((item) => {
                this.currentOptions.push(item);
            });
        }
    }
});
