import template from './sw-condition-date-range.html.twig';
import './sw-condition-date-range.scss';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the DateRangeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-date-range :condition="condition" :level="0"></sw-condition-date-range>
 */
Component.extend('sw-condition-date-range', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.withTime'),
                    value: true
                },
                {
                    label: this.$tc('global.sw-condition.condition.withoutTime'),
                    value: false
                }
            ];
        },

        useTime: {
            get() {
                this.ensureValueExist();
                if (typeof this.condition.value.useTime === 'undefined') {
                    this.condition.value = { ...this.condition.value, useTime: false };
                }

                return this.condition.value.useTime;
            },
            set(useTime) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, useTime };
            }
        },

        fromDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.fromDate || null;
            },
            set(fromDate) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, fromDate };
            }
        },

        toDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.toDate || null;
            },
            set(toDate) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, toDate };
            }
        },

        isDateTime() {
            return this.useTime ? 'datetime' : 'date';
        },

        ...mapApiErrors('condition', ['value.useTime', 'value.fromDate', 'value.toDate']),

        currentError() {
            return this.conditionValueUseTimeError || this.conditionValueFromDateError || this.conditionValueToDateError;
        }
    }
});
