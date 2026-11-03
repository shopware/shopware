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
    name: 'sw-sorting-select',
    load: () => import('./src/sw-sorting-select'),
    public: {
        props: [
            'sortBy',
            'sortDirection',
            'additionalSortOptions',
        ],
        emits: [
            'sorting-changed',
        ],
        blocks: [
            'sw_sorting_select',
        ],
        computed: [
            'sortOptions',
            'sortingConditionConcatenation',
            'sortingConditionOptions',
        ],
        methods: [
            'onSortingChanged',
        ],
    },
});
