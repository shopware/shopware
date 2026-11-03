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
    name: 'sw-inherit-wrapper',
    load: () => import('./src/sw-inherit-wrapper'),
    public: {
        props: [
            'value',
            'inheritedValue',
            'disabled',
            'label',
            'required',
            'isAssociation',
            'hasParent',
            'customInheritationCheckFunction',
            'customRestoreInheritanceFunction',
            'customRemoveInheritanceFunction',
            'helpText',
            'error',
        ],
        emits: [
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'content',
        ],
        blocks: [
            'sw_inherit_wrapper',
            'sw_inherit_wrapper_toggle',
            'sw_inherit_wrapper_toggle_wrapper',
            'sw_inherit_wrapper_toggle_wrapper_field',
            'sw_inherit_wrapper_toggle_wrapper_label',
            'sw_inherit_wrapper_toggle_wrapper_help_text',
            'sw_inherit_wrapper_content',
        ],
        computed: [
            'currentValue',
            'isInheritField',
            'isInherited',
            'labelClasses',
        ],
        methods: [
            'updateCurrentValue',
            'updateValue',
            'toggleInheritance',
            'restoreInheritance',
            'removeInheritance',
        ],
    },
});
