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
    name: 'sw-multi-select-filter',
    load: () => import('./src/sw-multi-select-filter'),
    public: {
        props: [
            'filter',
            'active',
        ],
        emits: [
            'filter-update',
            'filter-reset',
        ],
        slots: [
            'selection-label-property',
            'result-item',
        ],
        blocks: [
            'sw_multi_select_filter',
            'sw_multi_select_filter_content',
            'sw_multi_select_filter_content_entity_select',
            'sw_multi_select_filter_content_slot_selection_label_property',
            'sw_multi_select_filter_content_slot_result_item',
            'sw_multi_select_filter_content_option_select',
        ],
        computed: [
            'isEntityMultiSelect',
            'labelProperty',
            'values',
        ],
        methods: [
            'changeValue',
            'resetFilter',
        ],
    },
});
