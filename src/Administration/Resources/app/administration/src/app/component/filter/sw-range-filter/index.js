import template from './sw-range-filter.html.twig';
import './sw-range-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-range-filter', {
    template,

    model: {
        prop: 'value',
        event: 'change'
    },

    props: {
        filter: {
            type: Object,
            required: true
        },

        active: {
            type: Boolean,
            required: true
        },

        isShowDivider: {
            type: Boolean,
            required: false,
            default: true
        },

        value: {
            type: Object,
            required: true
        }
    },

    computed: {
        columns() {
            return this.isShowDivider ? '1fr 12px 1fr' : '1fr';
        },

        gap() {
            return this.isShowDivider ? '4px' : '12px';
        }
    },

    watch: {
        value: {
            deep: true,
            handler(newValue, oldValue) {
                if (!newValue.from && !oldValue.from && !newValue.to && !oldValue.to) {
                    return;
                }

                this.updateFilter(newValue);
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateFilter(this.value);
        },

        changeFromValue(newValue) {
            const range = { ...this.value, from: newValue };
            this.$emit('change', range);
        },

        changeToValue(newValue) {
            const range = { ...this.value, to: newValue };
            this.$emit('change', range);
        },

        updateFilter(range) {
            if (!range.from && !range.to) {
                this.$emit('filter-reset');
                return;
            }

            const params = {
                ...(range.from ? { gte: range.from } : {}),
                ...(range.to ? { lte: range.to } : {})
            };

            const filterCriteria = [Criteria.range(this.filter.property, params)];
            this.$emit('filter-update', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.$emit('change', { from: null, to: null });
            this.$emit('filter-reset');
        }
    }
});
