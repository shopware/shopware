import startCase from 'lodash-es/startCase';
import template from './sw-condition-field-errors.html.twig';
import './sw-condition-field-errors.scss';

const VISIBLE_LIMIT = 5;

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default {
    template,

    inject: {
        conditionType: { default: null },
    },

    props: {
        errors: {
            type: Object,
            required: true,
        },
    },

    computed: {
        labels() {
            return Object.entries(this.errors)
                .filter(
                    ([
                        ,
                        error,
                    ]) => !!error,
                )
                .map(([field]) => this.resolveLabel(field));
        },

        visibleLabels() {
            return this.labels.slice(0, VISIBLE_LIMIT);
        },

        hiddenCount() {
            return Math.max(this.labels.length - VISIBLE_LIMIT, 0);
        },

        summary() {
            if (!this.labels.length) {
                return '';
            }

            const visible = this.visibleLabels.join(', ');

            if (this.hiddenCount === 0) {
                return visible;
            }

            return `${visible} ${this.$t('global.sw-condition.errors.andMore', { count: this.hiddenCount }, this.hiddenCount)}`;
        },
    },

    methods: {
        resolveLabel(field) {
            const candidates = [
                `global.sw-condition-generic.${this.conditionType}.${field}.label`,
                `global.sw-condition.${this.conditionType}.${field}`,
                `global.sw-condition.field.${field}`,
            ];

            const match = candidates.find((path) => this.$te(path));

            if (match) {
                return this.$t(match);
            }

            return startCase(field);
        },
    },
};
