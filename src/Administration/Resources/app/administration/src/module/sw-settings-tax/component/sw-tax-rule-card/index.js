import template from './sw-tax-rule-card.html.twig';
import './sw-tax-rule-card.scss';

/**
 * @package checkout
 */

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    props: {
        tax: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            taxRulesLoading: false,
            cardLoading: false,
            taxRules: [],
            showModal: false,
            showDeleteModal: false,
            currentRule: null,
            term: '',
            page: 1,
            limit: 25,
            sortBy: 'country.name',
            sortDirection: 'ASC',
            total: undefined,
        };
    },

    computed: {
        taxRuleRepository() {
            return this.repositoryFactory.create('tax_rule');
        },

        taxRulesEmpty() {
            return this.taxRules.length === 0 && !this.term;
        },

        taxRuleCardClasses() {
            return {
                'sw-tax-rule-card--is-empty': this.taxRulesEmpty,
            };
        },

        taxRuleCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            criteria.addAssociation('type');
            criteria.addAssociation('country');
            criteria.addFilter(Criteria.equals('taxId', this.tax.id));

            if (this.term) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('taxRate', this.term),
                    Criteria.contains('type.technicalName', this.term),
                    Criteria.contains('type.typeName', this.term),
                    Criteria.contains('country.name', this.term),
                ]));
            }

            return criteria;
        },

        getColumns() {
            return [{
                property: 'country.name',
                dataIndex: 'country.name',
                label: 'sw-settings-tax.taxRuleCard.labelCountryName',
                primary: true,
            }, {
                property: 'type.typeName',
                dataIndex: 'type.typeName',
                label: 'sw-settings-tax.taxRuleCard.labelAppliesOn',
            }, {
                property: 'taxRate',
                dataIndex: 'taxRate',
                label: 'sw-settings-tax.taxRuleCard.labelTaxRate',
            }, {
                property: 'activeFrom',
                dataIndex: 'activeFrom',
                label: 'sw-settings-tax.taxRuleCard.labelActiveFrom',
            }];
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.taxRulesLoading = true;

            return this.taxRuleRepository.search(this.taxRuleCriteria, Context.api).then((response) => {
                this.total = response.total;
                this.taxRules = response;
                this.taxRulesLoading = false;
                return Promise.resolve();
            });
        },

        paginate({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        onColumnSort(column) {
            if (this.sortDirection === 'ASC' && column.dataIndex === this.sortBy) {
                this.sortDirection = 'DESC';
            } else {
                this.sortDirection = 'ASC';
            }

            this.sortBy = column.dataIndex;
            this.getList();
        },

        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
            this.getList();
        },

        onModalClose() {
            this.showModal = false;
            this.currentRule = null;
            this.$nextTick(() => this.getList());
        },

        showRuleModal(taxRule) {
            this.currentRule = taxRule;
            this.showModal = true;
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.taxRuleRepository.delete(id, Context.api).then(() => {
                this.getList();
            });
        },

        getTypeCellComponent(taxRule) {
            const subComponentName = taxRule.type.technicalName.replace(/_/g, '-');

            if (this.feature.isActive('VUE3')) {
                return Shopware.Component.getComponentRegistry().get(`sw-settings-tax-rule-type-${subComponentName}-cell`);
            }

            return this.$options.components[`sw-settings-tax-rule-type-${subComponentName}-cell`];
        },
    },
};
