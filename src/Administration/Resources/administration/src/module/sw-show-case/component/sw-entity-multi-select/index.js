import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import utils from 'src/core/service/util.service';

Component.extend('sw-entity-multi-select', 'sw-multi-select', {
    inject: ['repositoryFactory', 'context'],

    props: {
        options: {
            type: Array,
            required: false
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'name'
        },
        keyProperty: {
            type: String,
            required: false,
            default: 'id'
        },
        collection: {
            type: Object,
            required: true
        },
        resultLimit: {
            type: Number,
            required: false,
            default: 10
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

    methods: {

        initData() {
            this.repository = this.repositoryFactory.create(this.collection.entity, this.collection.source);
            this.searchRepository = this.repositoryFactory.create(this.collection.entity);

            this.collection.criteria.setLimit(this.valueLimit);
            this.searchCriteria = new Criteria(1, this.resultLimit);

            this.selectedIds = this.collection.getIds();

            this.repository.on('loaded', (result) => {
                this._displayAssigned(result);
            });

            this.repository.search(this.collection.criteria, this.collection.context);
        },

        loadVisibleItems() {
            this.collection.criteria.setPage(
                this.collection.criteria.page + 1
            );

            this.repository.search(
                this.collection.criteria,
                this.collection.context
            )
        },

        search() {
            this.searchCriteria.setTerm(this.searchTerm);
            this.searchCriteria.setPage(1);
            this._sendSearchRequest();
        },

        isSelected(item) {
            return this.selectedIds.includes(item[this.keyProperty]);
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
            this._sendSearchRequest();
        },

        paginate() {
            this.searchCriteria.setPage(this.searchCriteria.page + 1);
            this._sendSearchRequest();
        },

        remove(identifier) {
            this.repository.delete(identifier, this.collection.context);

            // remove identifier from visible element list
            this.visibleValues = this.visibleValues.filter((item) => {
                return item[this.keyProperty] !== identifier;
            });
        },

        addItem({ item }) {
            this.visibleValues.push(item);
            this.selectedIds.push(item[this.keyProperty]);

            return this.repository.assign(item[this.keyProperty], this.collection.context);
        },

        _sendSearchRequest() {
            return this.searchRepository.search(this.searchCriteria, this.context)
                .then((searchResult) => {

                    console.log(this.searchCriteria.limit, searchResult.length);
                    if (searchResult.length <= 0) {
                        return searchResult;
                    }

                    const criteria = new Criteria();
                    criteria.setIds(searchResult.getIds());

                    return this.repository.searchIds(criteria, this.collection.context).then((assigned) => {
                        assigned.data.forEach((id) => {
                            this.selectedIds.push(id);
                        });

                        this._displaySearch(searchResult);

                        return searchResult;
                    });
                });
        },

        _displayAssigned(result) {
            result.forEach((item) => {
                this.selectedIds.push(item[this.keyProperty]);
                this.visibleValues.push(item);
            });

            this.invisibleValueCount = result.total - this.visibleValues.length;
        },

        _displaySearch(result) {
            result.forEach((item) => {
                this.currentOptions.push(item);
            });
        }
    }
});
