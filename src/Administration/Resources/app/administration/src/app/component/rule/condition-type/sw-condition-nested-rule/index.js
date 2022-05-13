import template from './sw-condition-nested-rule.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-nested-rule', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    computed: {
        operators() {
            // multiStore operator labels work better in wording, even though just one rule can be chosen per condition
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        ruleId: {
            get() {
                this.ensureValueExist();
                return this.condition.value.ruleId || null;
            },
            set(ruleId) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, ruleId: ruleId };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.ruleId']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueRuleIdError;
        },
    },
});
