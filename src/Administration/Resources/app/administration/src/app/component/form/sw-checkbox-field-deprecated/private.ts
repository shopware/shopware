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
    name: 'sw-checkbox-field-deprecated',
    load: () => import('./src/sw-checkbox-field-deprecated'),
    public: {
        props: [
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
            'ariaLabel',
        ],
        emits: [
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'label',
            'hint',
        ],
        blocks: [
            'sw_checkbox_field',
        ],
        computed: [
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
            'onChange',
        ],
    },
});
