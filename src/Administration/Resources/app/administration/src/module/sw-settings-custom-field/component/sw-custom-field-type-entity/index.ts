import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-custom-field-type-select,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-type-entity',
    extends: 'sw-custom-field-type-select',
    load: () => import('./src/sw-custom-field-type-entity'),
    public: {
        props: [
            'currentCustomField',
            'set',
            'disabled',
        ],
        blocks: [
            'sw_custom_field_type_base_content',
            'sw_custom_field_type_select_options',
            'sw_custom_field_type_select_add_option',
            'sw_custom_field_type_select_multi',
            'sw_custom_field_type_entity_type',
            'sw_custom_field_type_entity_multi',
            'sw_custom_field_type_select_options_label',
            'sw_custom_field_type_select_options_delete',
            'sw_custom_field_type_select_options_container',
            'sw_custom_field_type_select_options_container_technical_name',
            'sw_custom_field_type_select_options_container_labels',
            'sw_custom_field_type_base',
            'sw_custom_field_type_base_labels',
        ],
        computed: [
            'entityTypes',
            'customFieldsAwareCustomEntities',
            'customEntityRepository',
            'sortedEntityTypes',
            'isOptionAddable',
            'locales',
        ],
        methods: [
            'createdComponent',
            'onChangeEntityType',
            'onChangeMultiSelectSwitch',
            'addOption',
            'onClickAddOption',
            'getLabel',
            'onDeleteOption',
        ],
    },
});
