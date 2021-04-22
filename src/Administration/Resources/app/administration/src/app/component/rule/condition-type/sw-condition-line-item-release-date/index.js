import template from './sw-condition-line-item-release-date.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-release-date', 'sw-condition-base', {
    template,

    data() {
        return {
            /**
             * @deprecated tag:v6.5.0 - will be removed without replacement
             */
            datepickerConfig: {
                enableTime: true,
                dateFormat: 'H:i',
                altFormat: 'H:i',
            },
            inputKey: 'lineItemReleaseDate',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('date'),
            );
        },

        lineItemReleaseDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.lineItemReleaseDate || null;
            },
            set(lineItemReleaseDate) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, lineItemReleaseDate };
            },
        },

        ...mapPropertyErrors('condition', ['value.useTime', 'value.lineItemReleaseDate']),

        currentError() {
            return this.conditionValueLineItemReleaseDateError;
        },
    },
});
