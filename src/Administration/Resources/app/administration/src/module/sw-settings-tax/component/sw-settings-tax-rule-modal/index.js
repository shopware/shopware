import template from './sw-settings-tax-rule-modal.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-tax-rule-modal', {
    template,

    inject: ['repositoryFactory'],

    props: {
        tax: {
            type: Object,
            required: true,
        },
        currentRule: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showModal: false,
            taxRule: null,
            currentTaxRuleType: null,
        };
    },

    computed: {
        taxRuleRepository() {
            return this.repositoryFactory.create('tax_rule');
        },
        taxRuleTypeRepository() {
            return this.repositoryFactory.create('tax_rule_type');
        },
        additionalComponent() {
            if (!this.currentTaxRuleType) {
                return null;
            }
            const subComponentName = this.currentTaxRuleType.technicalName.replace(/_/g, '-');
            return this.$options.components[`sw-settings-tax-rule-type-${subComponentName}`];
        },
        taxRuleTypeCriteria() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('position', 'ASC'));

            return criteria;
        },

        ...mapPropertyErrors('taxRule', ['taxRuleTypeId', 'countryId', 'taxRate']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        changeRuleType(id) {
            this.taxRuleTypeRepository.get(id, Context.api).then((item) => {
                this.currentTaxRuleType = item;
            });
        },
        createdComponent() {
            if (this.currentRule) {
                this.taxRule = this.currentRule;
                if (this.taxRule.taxRuleTypeId) {
                    this.changeRuleType(this.taxRule.taxRuleTypeId);
                }
            } else {
                this.taxRule = this.taxRuleRepository.create();
                this.taxRule.taxId = this.tax.id;
            }
        },

        onConfirm() {
            this.taxRuleRepository.save(this.taxRule, Context.api).then(() => {
                this.isSaveSuccessful = true;

                this.$emit('modal-close');
            }).catch(() => {
                this.isLoading = false;
            });
        },
    },
});
