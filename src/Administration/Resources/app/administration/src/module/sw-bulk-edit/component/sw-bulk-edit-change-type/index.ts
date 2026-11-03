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
    name: 'sw-bulk-edit-change-type',
    load: () => import('./src/sw-bulk-edit-change-type'),
    public: {
        props: [
            'value',
            'allowOverwrite',
            'allowClear',
            'allowAdd',
            'allowRemove',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'value-field',
        ],
        blocks: [
            'sw_bulk_edit_change_type',
            'sw_bulk_edit_change_type_select_field',
            'sw_bulk_edit_change_type_value_field',
        ],
        computed: [
            'currentValue',
            'options',
        ],
        methods: [
            'onChangeType',
        ],
    },
});
