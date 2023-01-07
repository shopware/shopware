import template from './sw-settings-rule-add-assignment-listing.html.twig';
import './sw-settings-rule-add-assignment-listing.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

/**
 * @private
 * @package business-ops
 */
export default {
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
            const criteria = new Criteria(this.page, this.limit);

            if (this.entityContext.addContext.association) {
                criteria.addAssociation(this.entityContext.addContext.association);
                criteria.getAssociation(this.entityContext.addContext.association)
                    .addFilter(Criteria.equals('id', this.ruleId));
            }

            if (this.entityContext.entityName === 'product') {
                criteria.addAssociation('options.group');
            }

            return criteria;
        },

        shippingCostTaxOptions() {
            return [{
                label: this.$tc('sw-settings-shipping.shippingCostOptions.auto'),
                value: 'auto',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.highest'),
                value: 'highest',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.fixed'),
                value: 'fixed',
            }];
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

        doSearch(term) {
            this.loading = true;
            const api = this.entityContext.api ? this.entityContext.api() : Context.api;
            const criteria = cloneDeep(this.criteria);

            if (term) {
                criteria.addFilter(Criteria.contains(this.entityContext.addContext.searchColumn ?? 'name', term));
            }

            return this.repository.search(criteria, api).then((result) => {
                this.items = result;
                this.total = result.total;
            }).finally(() => {
                this.loading = false;
            });
        },

        shippingTaxTypeLabel(taxName) {
            if (!taxName) {
                return '';
            }

            const tax = this.shippingCostTaxOptions.find((i) => taxName === i.value) || '';

            return tax?.label;
        },
    },
};
