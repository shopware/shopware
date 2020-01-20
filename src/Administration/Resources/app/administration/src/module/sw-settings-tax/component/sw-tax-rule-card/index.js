import template from './sw-tax-rule-card.html.twig';
import './sw-tax-rule-card.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-tax-rule-card', {
    template,

    inject: [
        'repositoryFactory'
    ],

    props: {
        tax: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            taxRulesLoading: false,
            cardLoading: false,
            taxRules: [],
            showModal: false,
            showDeleteModal: false,
            currentRule: null,
            term: ''
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
                'sw-tax-rule-card--is-empty': this.taxRulesEmpty
            };
        },

        taxRuleCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('country.id', 'ASC'));
            criteria.addSorting(Criteria.sort('type.position', 'ASC'));
            criteria.addAssociation('type');
            criteria.addAssociation('country');
            criteria.addFilter(Criteria.equals('taxId', this.tax.id));

            if (this.term) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('taxRate', this.term),
                    Criteria.contains('type.technicalName', this.term),
                    Criteria.contains('type.typeName', this.term),
                    Criteria.contains('country.name', this.term)
                ]));
            }

            return criteria;
        },

        getColumns() {
            return [{
                property: 'country.name',
                dataIndex: 'country.name',
                label: 'sw-settings-tax.taxRuleCard.labelCountryName',
                primary: true
            }, {
                property: 'type.typeName',
                dataIndex: 'type.typeName',
                label: 'sw-settings-tax.taxRuleCard.labelAppliesOn'
            }, {
                property: 'taxRate',
                dataIndex: 'taxRate',
                label: 'sw-settings-tax.taxRuleCard.labelTaxRate'
            }];
        }
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
            return this.$options.components[`sw-settings-tax-rule-type-${subComponentName}-cell`];
        }
    }
});
