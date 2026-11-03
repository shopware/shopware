import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-form-field-renderer,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-bulk-edit-form-field-renderer',
    extends: 'sw-form-field-renderer',
    load: () => import('./src/sw-bulk-edit-form-field-renderer'),
    public: {
        props: [
            'type',
            'config',
            'value',
            'error',
        ],
        emits: [
            'update:default-unit',
            'update:value',
        ],
        slots: [
            'slotName',
            'default',
        ],
        blocks: [
            'sw_form_field_renderer',
            'sw_form_field_renderer_inner',
            'sw_form_field_renderer_scope_slots',
            'sw_form_field_renderer_inner_slot',
        ],
        computed: [
            'bind',
            'hasConfig',
            'componentName',
            'swFieldType',
            'translations',
            'optionTranslations',
            'componentPropName',
        ],
        methods: [
            'onUpdateDefaultUnit',
            'createdComponent',
            'emitUpdate',
            'getTranslations',
            'getComponentFromType',
            'createRepository',
            'fetchSystemCurrency',
            'getScopedSlots',
        ],
    },
});
