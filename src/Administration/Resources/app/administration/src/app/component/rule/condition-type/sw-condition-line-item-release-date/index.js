import template from './sw-condition-line-item-release-date.html.twig';
import './sw-condition-line-item-release-date.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-release-date', 'sw-condition-base', {
    template,

    computed: {
        lineItemReleaseDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.lineItemReleaseDate || null;
            },
            set(lineItemReleaseDate) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, lineItemReleaseDate };
            }
        },

        ...mapPropertyErrors('condition', ['value.useTime', 'value.lineItemReleaseDate']),

        currentError() {
            return this.conditionValueLineItemReleaseDateError;
        }
    }
});
