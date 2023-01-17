import template from './sw-range-filter.html.twig';
import './sw-range-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-range-filter', {
    template,

    inject: ['feature'],

    props: {
        value: {
            type: Object,
            required: true,
        },

        property: {
            type: String,
            required: true,
        },

        isShowDivider: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        /**
         * @deprecated tag:v6.5.0 - will be dropped
         */
        columns() {
            return this.isShowDivider ? '1fr 12px 1fr' : '1fr';
        },

        /**
         * @deprecated tag:v6.5.0 - will be dropped
         */
        gap() {
            return this.isShowDivider ? '4px' : '12px';
        },
    },

    watch: {
        value: {
            deep: true,
            handler(newValue) {
                this.updateFilter(newValue);
            },
        },
    },

    methods: {
        updateFilter(range) {
            const params = {
                ...(range.from ? { gte: range.from } : {}),
                ...(range.to ? { lte: range.to } : {}),
            };

            const filterCriteria = [Criteria.range(this.property, params)];
            this.$emit('filter-update', filterCriteria);
        },
    },
});
