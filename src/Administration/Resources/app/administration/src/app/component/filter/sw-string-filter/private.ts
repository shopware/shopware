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
    name: 'sw-string-filter',
    load: () => import('./src/sw-string-filter'),
    public: {
        props: [
            'filter',
            'active',
            'criteriaFilterType',
        ],
        emits: [
            'filter-update',
            'filter-reset',
        ],
        blocks: [
            'sw_string_filter',
            'sw_string_filter_content',
        ],
        methods: [
            'updateFilter',
            'resetFilter',
        ],
    },
});
