import template from './sw-condition-is-newsletter-recipient.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the IsNewsletterRecipientRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-newsletter-recipient :condition="condition" :level="0"></sw-condition-is-newsletter-recipient>
 */
Component.extend('sw-condition-is-newsletter-recipient', 'sw-condition-base', {
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

        isNewsletterRecipient: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isNewsletterRecipient;
            },
            set(isNewsletterRecipient) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isNewsletterRecipient };
            },
        },

        ...mapPropertyErrors('condition', ['value.isNewsletterRecipient']),

        currentError() {
            return this.conditionValueIsNewsletterRecipientError;
        },
    },
});
