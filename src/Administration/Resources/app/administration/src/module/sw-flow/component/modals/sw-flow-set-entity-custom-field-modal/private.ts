import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-set-entity-custom-field-modal',
    load: () => import('./src/sw-flow-set-entity-custom-field-modal'),
    public: {
        props: [
            'sequence',
            'action',
        ],
        emits: [
            'modal-close',
            'process-finish',
        ],
        blocks: [
            'sw_flow_set_entity_custom_field_modal',
            'sw_flow_set_entity_custom_field_modal_content',
            'sw_flow_tag_modal_to_field',
            'sw_flow_set_entity_custom_field_modal_category',
            'sw_flow_set_entity_custom_field_modal_field_name',
            'sw_flow_set_entity_custom_field_modal_field_value_options',
            'sw_flow_set_entity_custom_field_modal_field_value',
            'sw_flow_set_entity_custom_field_modal_custom',
            'sw_flow_set_entity_custom_field_modal_footer',
            'sw_flow_set_entity_custom_field_modal_footer_cancel_button',
            'sw_flow_set_entity_custom_field_modal_footer_save_button',
        ],
        computed: [
            'customFieldRepository',
            'customFieldCriteria',
            'customFieldSetCriteria',
            'showFieldValue',
            'defaultFieldOptions',
            'multipleFieldOptions',
            'labelProperty',
        ],
        methods: [
            'createdComponent',
            'getCustomFieldRendered',
            'onEntityChange',
            'onCustomFieldSetChange',
            'onCustomFieldChange',
            'validateOptionSelectFieldLabel',
            'onClose',
            'onAddAction',
            'fieldError',
            'getFieldOptions',
            'getEntityOptions',
            'convertToEntityTechnicalName',
        ],
    },
});
