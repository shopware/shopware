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
    name: 'sw-entity-multi-id-select',
    load: () => import('./src/sw-entity-multi-id-select'),
    public: {
        props: [
            'value',
            'repository',
            'criteria',
            'context',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'selection-label-property',
            'before-item-list',
            'result-label-property',
            'result-description-property',
            'after-item-list',
            'label',
            'hint',
        ],
        blocks: [
            'sw_entity_multi_id_select',
            'sw_entity_multi_id_select_label_property',
            'sw_entity_multi_id_select_results_list_before',
            'sw_entity_multi_id_select_results_list_result_label',
            'sw_entity_multi_id_select_results_list_result_description',
            'sw_entity_multi_id_select_results_list_after',
        ],
        computed: [
            'normalizedValue',
            'displayVariants',
            'selectCriteria',
        ],
        methods: [
            'createdComponent',
            'updateIds',
            'displayLabelProperty',
        ],
    },
});
