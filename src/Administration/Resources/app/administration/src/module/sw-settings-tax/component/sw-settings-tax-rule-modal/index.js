import template from './sw-settings-tax-rule-modal.html.twig';

/**
 * @package checkout
 */

const { Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'feature'],

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

            if (this.feature.isActive('VUE3')) {
                return `sw-settings-tax-rule-type-${subComponentName}`;
            }

            return this.$options.components[`sw-settings-tax-rule-type-${subComponentName}`];
        },
        taxRuleTypeCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('position', 'ASC'));

            return criteria;
        },
        countryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        ...mapPropertyErrors('taxRule', ['taxRuleTypeId', 'countryId', 'taxRate', 'activeFrom']),
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
};
