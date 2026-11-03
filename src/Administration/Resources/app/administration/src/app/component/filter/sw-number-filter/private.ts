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
    name: 'sw-number-filter',
    load: () => import('./src/sw-number-filter'),
    public: {
        props: [
            'filter',
            'active',
        ],
        emits: [
            'filter-reset',
            'filter-update',
        ],
        blocks: [
            'sw_number_filter',
            'sw_number_filter_from_field',
            'sw_number_filter_to_field',
        ],
        methods: [
            'fromToFieldLabel',
            'updateFilter',
            'resetFilter',
        ],
    },
});
