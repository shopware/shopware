import Criteria from 'src/core/data-new/criteria.data';
import utils from 'src/core/service/util.service';

export default {
    name: 'sw-entity-single-select',
    extendsFrom: 'sw-single-select',

    inject: ['repositoryFactory', 'context'],

    props: {
        options: {
            required: false,
            type: [Array, Object],
            default() {
                return [];
            }
        },
        entity: {
            required: true,
            type: String
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'id'
        },
        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        }
    },

    data() {
        return {
            silent: false,
            page: 1,
            limit: 10,
            repository: {}
        };
    },

    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory.create(this.entity);

            this.$on('scroll', this.paginate);

            this.$super.createdComponent();
        },

        resolveKey(key) {
            this.silent = true;

            return this.repository.get(key, this.context).then((item) => {
                this.silent = false;
                return item;
            });
        },

        paginate(event) {
            if (this.getDistFromBottom(event.target) !== 0) {
                return;
            }

            this.page += 1;
            this.load();
        },

        applyResult(result) {
            result.forEach((item) => {
                this.currentOptions.push(item);
            });

            this.initPlaceholder();

            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
        },

        openResultList() {
            if (this.isExpanded === false) {
                this.currentOptions = [];
                this.page = 1;

                this.$super.openResultList();

                return this.load();
            }

            return this.$super.openResultList();
        },

        load() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.setTotalCountMode(0);
            criteria.setTerm(this.searchTerm);

            return this.repository.search(criteria, this.context).then((result) => {
                if (this.silent) {
                    return;
                }

                this.applyResult(result);
                this.isLoading = false;
            });
        },

        search: utils.debounce(function debouncedSearch() {
            this.currentOptions = [];
            this.page = 1;
            this.load();
        }, 400),

        getDistFromBottom(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        }
    }
};
