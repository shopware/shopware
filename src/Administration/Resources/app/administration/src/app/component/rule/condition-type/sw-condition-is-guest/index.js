import template from './sw-condition-is-guest.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the IsGuestRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-company :condition="condition" :level="0"></sw-condition-is-company>
 */
Component.extend('sw-condition-is-guest', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },

        isGuest: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isGuest;
            },
            set(isGuest) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isGuest };
            },
        },

        ...mapPropertyErrors('condition', ['value.isGuest']),

        currentError() {
            return this.conditionValueIsGuestError;
        },
    },
});
