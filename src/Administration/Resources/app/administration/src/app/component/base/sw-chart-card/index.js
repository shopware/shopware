import template from './sw-chart-card.html.twig';
import './sw-chart-card.scss';

const defaultRanges = ['30Days', '14Days', '7Days', '24Hours', 'yesterday'];

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @description
 * Layout-wrapper for sw-card and sw-chart.
 * This component provides specific props for the card configuration and range dropdown.
 * It contains no logic for managing chart datasets,
 * but provides an event "sw-chart-card-range-update" to refresh data based on the selected range.
 *
 * All further attributes on this component are passed down to the child "sw-chart".
 * Please refer to the documentation of "sw-chart" for proper configuration.
 */
Shopware.Component.register('sw-chart-card', {
    template,
    props: {
        availableRanges: {
            type: Array,
            default: () => {
                return defaultRanges;
            },
        },
        cardSubtitle: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            selectedRange: this.availableRanges[0],
        };
    },
    computed: {
        hasHeaderLink() {
            return !!this.$slots['header-link'];
        },
    },
    methods: {
        dispatchRangeUpdate() {
            this.$emit('sw-chart-card-range-update', this.selectedRange);
        },
    },
});
