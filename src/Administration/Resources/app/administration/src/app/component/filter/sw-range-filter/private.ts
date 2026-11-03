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
    name: 'sw-range-filter',
    load: () => import('./src/sw-range-filter'),
    public: {
        props: [
            'value',
            'property',
            'isShowDivider',
        ],
        emits: [
            'filter-update',
        ],
        slots: [
            'default',
            'from-field',
            'divider',
            'to-field',
        ],
        blocks: [
            'sw_range_filter',
            'sw_range_filter_content',
            'sw_range_filter_from_field',
            'sw_range_filter_divider',
            'sw_range_filter_to_field',
        ],
        methods: [
            'updateFilter',
        ],
    },
});
