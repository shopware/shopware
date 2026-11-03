import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-chart-card',
    load: () => import('./src/sw-chart-card'),
    public: {
        props: [
            'availableRanges',
            'defaultRangeIndex',
            'cardTitle',
            'cardSubtitle',
            'positionIdentifier',
            'helpText',
            'isLoading',
        ],
        emits: [
            'sw-chart-card-range-update',
        ],
        slots: [
            'header-title',
            'header-link',
            'range-option',
        ],
        blocks: [
            'sw_chart_card',
            'sw_chart_card_title',
            'sw_chart_card_title_slot',
            'sw_chart_card_title_help_text',
            'sw_chart_card_header',
            'sw_chart_card_header_slot_link',
            'sw_chart_card_header_link_arrow',
            'sw_chart_card_header_range_select',
            'sw_chart_card_header_range_select_options',
            'sw_chart_card_inner_chart',
        ],
        computed: [
            'hasHeaderLink',
            'rangeOptions',
        ],
        methods: [
            'dispatchRangeUpdate',
            'extractSlotText',
        ],
    },
});
