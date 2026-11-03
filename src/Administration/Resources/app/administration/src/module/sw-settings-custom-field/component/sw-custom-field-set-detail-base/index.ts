import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-set-detail-base',
    load: () => import('./src/sw-custom-field-set-detail-base'),
    public: {
        props: [
            'set',
            'technicalNameError',
        ],
        emits: [
            'reset-errors',
        ],
        blocks: [
            'sw_settings_custom_field_set_detail_base',
            'sw_settings_custom_field_set_detail_base_technical_name',
            'sw_settings_custom_field_set_detail_base_position',
            'sw_settings_custom_field_set_detail_base_translated',
            'sw_settings_custom_field_set_detail_base_labels',
            'sw_settings_custom_field_set_detail_base_multi_select',
            'sw_settings_custom_field_set_detail_base_entities',
        ],
        computed: [
            'locales',
            'customFieldSetRelationRepository',
            'selectedRelationEntityNames',
            'relationEntityNames',
        ],
        methods: [
            'onAddRelation',
            'onRemoveRelation',
            'searchRelationEntityNames',
            'onTechnicalNameChange',
        ],
    },
});
