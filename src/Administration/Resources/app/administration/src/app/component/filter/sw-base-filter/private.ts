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
    name: 'sw-base-filter',
    load: () => import('./src/sw-base-filter'),
    public: {
        props: [
            'title',
            'showResetButton',
            'active',
        ],
        emits: [
            'filter-reset',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_base_filter',
            'sw_base_filter_headline',
            'sw_base_filter_content',
        ],
        methods: [
            'resetFilter',
        ],
    },
});
