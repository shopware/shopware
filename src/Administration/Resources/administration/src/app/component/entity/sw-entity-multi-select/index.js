import Criteria from 'src/core/data-new/criteria.data';

export default {
    name: 'sw-entity-multi-select',
    extendsFrom: 'sw-multi-select',

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

            return this.repository.search(this.collection.criteria, this.collection.context);
        },

        loadVisibleItems() {
            this.collection.criteria.setPage(
                this.collection.criteria.page + 1
            );

            return this.repository.search(
                this.collection.criteria,
                this.collection.context
            );
        },

        search() {
            this.searchCriteria.setTerm(this.searchTerm);
            this.searchCriteria.setPage(1);
            this.currentOptions = [];
            return this._sendSearchRequest();
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
            return this._sendSearchRequest();
        },

        paginate() {
            this.searchCriteria.setPage(this.searchCriteria.page + 1);
            return this._sendSearchRequest();
        },

        remove(identifier) {
            // remove identifier from visible element list
            this.visibleValues = this.visibleValues.filter((item) => {
                return item[this.keyProperty] !== identifier;
            });

            this.selectedIds = this.selectedIds.filter((id) => {
                return id !== identifier;
            });

            return this.repository.delete(identifier, this.collection.context);
        },

        addItem({ item }) {
            if (this.isSelected(item)) {
                this.remove(item[this.keyProperty]);
                return Promise.resolve();
            }

            this.visibleValues.push(item);
            this.selectedIds.push(item[this.keyProperty]);

            return this.repository.assign(item[this.keyProperty], this.collection.context);
        },

        _sendSearchRequest() {
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
};
