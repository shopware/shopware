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

            this.$on('scroll', this.paginate);

            this.displayAssigned(this.collection);

            if (this.localMode) {
                return Promise.resolve();
            }

            return this.repository.search(this.collection.criteria, this.collection.context).then(this.displayAssigned);
        },

        loadVisibleItems() {
            this.collection.criteria.setPage(this.collection.criteria.page + 1);

            return this.repository.search(this.collection.criteria, this.collection.context).then(this.displayAssigned);
        },

        search() {
            this.searchCriteria.setTerm(this.searchTerm);
            this.searchCriteria.setPage(1);
            this.currentOptions = [];
            return this.sendSearchRequest();
        },

        isSelected(item) {
            return this.selectedIds.includes(item[this.valueProperty]);
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

            if (this.localMode) {
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

            if (this.localMode) {
                this.collection.remove(identifier);
                return Promise.resolve();
            }

            return this.repository.delete(identifier, this.collection.context);
        },

        addItem({ item }) {
            if (this.isSelected(item)) {
                this.remove(item[this.valueProperty]);
                return Promise.resolve();
            }

            this.visibleValues.push(item);
            this.selectedIds.push(item[this.valueProperty]);

            if (this.localMode) {
                this.collection.add(item);
                return Promise.resolve();
            }

            return this.repository.assign(item[this.valueProperty], this.collection.context);
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
};
