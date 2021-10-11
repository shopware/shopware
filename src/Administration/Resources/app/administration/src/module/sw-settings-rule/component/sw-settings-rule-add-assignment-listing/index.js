import template from './sw-settings-rule-add-assignment-listing.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-rule-add-assignment-listing', {
    template,

    props: {
        ruleId: {
            type: String,
            required: true,
        },

        entityContext: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            loading: true,
            repository: null,
            items: [],
            preselectedIds: [],
            limit: 10,
            page: 1,
            total: 0,
        };
    },

    computed: {
        criteria() {
            const criteria = new Criteria();
            criteria.setLimit(this.limit);
            criteria.setPage(this.page);

            if (this.entityContext.addContext.association) {
                criteria.addAssociation(this.entityContext.addContext.association);
                criteria.getAssociation(this.entityContext.addContext.association).addFilter(Criteria.equals('id', this.ruleId));
            }

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.repository = this.entityContext.repository;

            this.doSearch();
        },

        onSelectItem(selection, item, selected) {
            this.$emit('select-item', selection, item, selected);
        },

        isNotAssigned(item) {
            if (this.entityContext.addContext.association) {
                return item[this.entityContext.addContext.association].length <= 0;
            }

            return item[this.entityContext.addContext.column] !== this.ruleId;
        },

        paginate({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;

            return this.doSearch();
        },

        doSearch() {
            this.loading = true;
            const api = this.entityContext.api ? this.entityContext.api() : Context.api;
            return this.repository.search(this.criteria, api).then((result) => {
                this.items = result;
                this.total = result.total;
            }).finally(() => {
                this.loading = false;
            });
        },
    },
});
