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
    name: 'sw-existence-filter',
    load: () => import('./src/sw-existence-filter'),
    public: {
        props: [
            'filter',
            'active',
        ],
        emits: [
            'filter-update',
            'filter-reset',
        ],
        blocks: [
            'sw_existence_filter',
            'sw_existence_filter_content',
        ],
        computed: [
            'value',
            'filterOptions',
        ],
        methods: [
            'changeValue',
            'resetFilter',
        ],
    },
});
