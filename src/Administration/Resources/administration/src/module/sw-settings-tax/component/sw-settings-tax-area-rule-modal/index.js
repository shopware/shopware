import template from './sw-settings-tax-area-rule-modal.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-tax-area-rule-modal', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    props: {
        tax: {
            type: Object,
            required: true
        },
        currentAreaRule: {
            type: Object,
            required: false
        }
    },

    computed: {
        taxAreaRuleRepository() {
            return this.repositoryFactory.create('tax_area_rule');
        },
        taxAreaRuleTypeRepository() {
            return this.repositoryFactory.create('tax_area_rule_type');
        },
        additionalComponent() {
            if (!this.currentTaxAreaRuleType) {
                return null;
            }
            const subComponentName = this.currentTaxAreaRuleType.technicalName.replace(/_/g, '-');
            return this.$options.components[`sw-settings-tax-area-rule-type-${subComponentName}`];
        },

        ...mapApiErrors('taxAreaRule', ['taxAreaRuleTypeId', 'countryId', 'taxRate'])
    },

    data() {
        return {
            showModal: false,
            areaConfig: {},
            taxAreaRule: null,
            currentTaxAreaRuleType: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        changeRuleType(id) {
            this.taxAreaRuleTypeRepository.get(id, this.apiContext).then((item) => {
                this.currentTaxAreaRuleType = item;
            });
        },
        createdComponent() {
            if (this.currentAreaRule) {
                this.taxAreaRule = this.currentAreaRule;
                if (this.taxAreaRule.taxAreaRuleTypeId) {
                    this.changeRuleType(this.taxAreaRule.taxAreaRuleTypeId);
                }
            } else {
                this.taxAreaRule = this.taxAreaRuleRepository.create();
                this.taxAreaRule.taxId = this.tax.id;
            }
        },

        onConfirm() {
            this.taxAreaRuleRepository.save(this.taxAreaRule, this.apiContext).then(() => {
                this.isSaveSuccessful = true;

                this.$emit('modal-close');
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-tax.detail.notificationErrorTitle'),
                    message: this.$tc('sw-settings-tax.detail.notificationErrorMessage')
                });
                this.isLoading = false;
            });
        }
    }
});
