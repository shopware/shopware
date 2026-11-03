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
    name: 'sw-card-filter',
    load: () => import('./src/sw-card-filter'),
    public: {
        props: [
            'placeholder',
            'delay',
            'initialSearchTerm',
        ],
        emits: [
            'sw-card-filter-term-change',
        ],
        slots: [
            'filter',
        ],
        blocks: [
            'sw_card_search_bar',
            'sw_card_filter_bar_container',
            'sw_card_filter_bar_container_field',
            'sw_card_filter_bar_container_field_filter',
        ],
        computed: [
            'hasFilter',
            'hasFilterClass',
        ],
        methods: [
            'createdComponent',
            'onSearchTermChange',
        ],
    },
});
