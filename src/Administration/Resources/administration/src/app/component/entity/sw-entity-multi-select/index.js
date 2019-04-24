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
        entity: {
            type: String,
            required: true
        },
        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, this.resultLimit);
            }
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
            selectedIds: []
        };
    },

    methods: {
        initData() {
            this.repository = this.repositoryFactory.create(this.entity, `/${this.entity}`);

            this.$on('scroll', this.paginate);

            return Promise.resolve();
        },

        loadVisibleItems() {
            this.criteria.setPage(this.criteria.page + 1);

            return this.repository.search(this.criteria, this.context).then(this.displayAssigned);
        },

        search() {
            this.criteria.setTerm(this.searchTerm);
            this.criteria.setPage(1);
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
            return this.sendSearchRequest();
        },

        paginate(event) {
            if (this.getDistFromBottom(event.target) !== 0) {
                return Promise.resolve();
            }

            this.criteria.setPage(this.criteria.page + 1);
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

            this.$emit('input', this.visibleValues);

            return Promise.resolve();
        },

        addItem({ item }) {
            if (this.isSelected(item)) {
                this.remove(item[this.valueProperty]);
                return Promise.resolve();
            }

            this.visibleValues.push(item);
            this.selectedIds.push(item[this.valueProperty]);

            this.$emit('input', this.visibleValues);

            return Promise.resolve();
        },

        sendSearchRequest() {
            return this.repository.search(this.criteria, this.context)
                .then((searchResult) => {
                    if (searchResult.length <= 0) {
                        return searchResult;
                    }

                    this.displaySearch(searchResult);

                    return searchResult;
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
