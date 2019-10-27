import template from './sw-tax-area-rule-card.twig';
import './sw-tax-area-rule-card.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-tax-area-rule-card', {
    template,

    inject: [
        'repositoryFactory',
        'apiContext'
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder')
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
            taxAreaRulesLoading: false,
            cardLoading: false,
            taxAreaRules: [],
            taxAreaRuleTypes: null,
            showModal: false,
            showDeleteModal: false,
            currentAreaRule: null,
            term: ''
        };
    },

    computed: {
        taxAreaRuleTypeRepository() {
            return this.repositoryFactory.create('tax_area_rule_type');
        },

        taxAreaRuleRepository() {
            return this.repositoryFactory.create('tax_area_rule');
        },

        taxAreaRulesEmpty() {
            return this.taxAreaRules.length === 0 && !this.term;
        },

        taxAreaRuleCardStyles() {
            return `sw-tax-area-rule-card ${this.taxAreaRulesEmpty ? 'sw-tax-area-rule-card--is-empty' : ''}`;
        },

        taxAreaRuleTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('technicalName', 'ASC'));

            return criteria;
        },

        taxAreaRuleCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.addAssociation('taxAreaRuleType');
            criteria.addAssociation('country');
            criteria.addFilter(Criteria.equals('taxId', this.tax.id));

            if (this.term) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('taxRate', this.term),
                    Criteria.contains('taxAreaRuleType.technicalName', this.term),
                    Criteria.contains('taxAreaRuleType.typeName', this.term),
                    Criteria.contains('country.name', this.term)
                ]));
            }

            return criteria;
        },

        getColumns() {
            return [{
                property: 'country.name',
                dataIndex: 'country.name',
                label: this.$tc('sw-settings-tax.taxAreaRuleCard.labelCountryName'),
                primary: true
            }, {
                property: 'taxAreaRuleType.typeName',
                dataIndex: 'taxAreaRuleType.typeName',
                label: this.$tc('sw-settings-tax.taxAreaRuleCard.labelAppliesOn')
            }, {
                property: 'taxRate',
                dataIndex: 'taxRate',
                label: this.$tc('sw-settings-tax.taxAreaRuleCard.labelTaxRate')
            }];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cardLoading = true;

            this.taxAreaRuleTypeRepository.search(this.taxAreaRuleTypeCriteria, this.apiContext).then((response) => {
                this.taxAreaRuleTypes = response;
                this.cardLoading = false;
            });
        },

        getList() {
            this.taxAreaRulesLoading = true;

            return this.taxAreaRuleRepository.search(this.taxAreaRuleCriteria, this.apiContext).then((response) => {
                this.total = response.total;
                this.taxAreaRules = response;
                this.taxAreaRulesLoading = false;
                return Promise.resolve();
            });
        },

        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
            this.getList();
        },

        onModalClose() {
            this.showModal = false;
            this.currentAreaRule = null;
            this.$nextTick(() => this.getList());
        },

        showAreaRuleModal(taxAreaRule) {
            this.currentAreaRule = taxAreaRule;
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

            return this.taxAreaRuleRepository.delete(id, this.apiContext).then(() => {
                this.getList();
            });
        },

        getTypeCellComponent(taxAreaRule) {
            const subComponentName = taxAreaRule.taxAreaRuleType.technicalName.replace(/_/g, '-');
            return this.$options.components[`sw-settings-tax-area-rule-type-${subComponentName}-cell`];
        }
    }
});
