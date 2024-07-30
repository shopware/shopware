import template from './sw-chart-card.html.twig';
import './sw-chart-card.scss';

const defaultRanges = ['30Days', '14Days', '7Days', '24Hours', 'yesterday'];

/**
 * @package admin
 *
 * @private
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

    compatConfig: Shopware.compatConfig,

    props: {
        availableRanges: {
            type: Array,
            required: false,
            default: () => {
                return defaultRanges;
            },
        },
        defaultRangeIndex: {
            type: Number,
            required: false,
            default: () => {
                return 0;
            },
        },
        cardTitle: {
            type: String,
            required: false,
            default: '',
        },
        cardSubtitle: {
            type: String,
            required: false,
            default: '',
        },
        positionIdentifier: {
            type: String,
            required: true,
            default: '',
        },
        helpText: {
            type: [String, Object],
            required: false,
            default: () => {
                return '';
            },
        },
    },

    data() {
        return {
            selectedRange: this.availableRanges[this.defaultRangeIndex],
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
