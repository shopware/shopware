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
    name: 'sw-bulk-edit-change-type-field-renderer',
    load: () => import('./src/sw-bulk-edit-change-type-field-renderer'),
    public: {
        props: [
            'bulkEditData',
            'formFields',
            'entity',
            'disabled',
        ],
        emits: [
            'change-value',
            'update:default-unit',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'valueFieldWithBoxType',
            'valueField',
        ],
        blocks: [
            'sw_bulk_edit_change_type_field_renderer',
            'sw_bulk_edit_change_type_field_renderer_container',
            'sw_bulk_edit_change_type_field_renderer_change_field',
            'sw_bulk_edit_change_type_field_renderer_change_field_title',
            'sw_bulk_edit_change_type_field_renderer_change_field_subtitle',
            'sw_bulk_edit_change_type_field_renderer_change_form_value_field',
            'sw_bulk_edit_change_type_field_renderer_change_form_value_field_without_change_type',
        ],
        methods: [
            'hasFormFieldConfig',
            'getConfigValue',
            'showSelectBoxType',
            'onChangeValue',
            'onChangeToggle',
            'onInheritanceRestore',
            'onInheritanceRemove',
        ],
    },
});
