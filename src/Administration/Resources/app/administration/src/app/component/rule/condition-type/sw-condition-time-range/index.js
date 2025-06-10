import template from './sw-condition-time-range.html.twig';
import './sw-condition-time-range.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            datepickerConfig: {
                enableTime: true,
                dateFormat: 'H:i',
            },
        };
    },

    computed: {
        fromTime: {
            get() {
                this.ensureValueExist();

                return this.condition.value.fromTime || null;
            },
            set(fromTime) {
                this.ensureValueExist();
                this.condition.value.fromTime = fromTime;
            },
        },
        toTime: {
            get() {
                this.ensureValueExist();

                return this.condition.value.toTime || null;
            },
            set(toTime) {
                this.ensureValueExist();
                this.condition.value.toTime = toTime;
            },
        },

        ...mapPropertyErrors('condition', [
            'value.fromTime',
            'value.toTime',
        ]),

        currentError() {
            return this.conditionValueFromTimeError || this.conditionValueToTimeError;
        },
    },
};
