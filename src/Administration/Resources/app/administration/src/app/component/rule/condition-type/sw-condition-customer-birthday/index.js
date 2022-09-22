import template from './sw-condition-customer-birthday.html.twig';
import { mapPropertyErrors } from '../../../../service/map-errors.service';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic instead.
 */
Component.extend('sw-condition-customer-birthday', 'sw-condition-base', {
    template,

    data() {
        return {
            inputKey: 'birthday',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('date'),
            );
        },

        birthday: {
            get() {
                this.ensureValueExist();
                return this.condition.value.birthday || null;
            },
            set(birthday) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, birthday };
            },
        },

        ...mapPropertyErrors('condition', ['value.useTime', 'value.birthday']),
    },
});
