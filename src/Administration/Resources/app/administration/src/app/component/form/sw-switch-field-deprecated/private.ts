import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-checkbox-field-deprecated,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-switch-field-deprecated',
    extends: 'sw-checkbox-field-deprecated',
    load: () => import('./src/sw-switch-field-deprecated'),
    public: {
        props: [
            'noMarginTop',
            'size',
            'ariaLabel',
            'id',
            'disabled',
            'label',
            'value',
            'inheritedValue',
            'ghostValue',
            'error',
            'bordered',
            'padded',
            'partlyChecked',
        ],
        emits: [
            'inheritance-remove',
            'inheritance-restore',
            'update:value',
        ],
        slots: [
            'label',
            'hint',
        ],
        blocks: [
            'sw_checkbox_field',
            'sw_switch_field',
        ],
        computed: [
            'swSwitchFieldClasses',
            'swCheckboxFieldClasses',
            'swCheckboxFieldContentClasses',
            'identification',
            'hasError',
            'inputState',
            'isInheritanceField',
            'isInherited',
            'isPartlyChecked',
            'iconName',
            'attrsWithoutClass',
        ],
        methods: [
            'onInheritanceRestore',
            'onChange',
        ],
    },
});
