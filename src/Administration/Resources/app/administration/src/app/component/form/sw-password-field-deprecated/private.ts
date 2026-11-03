import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-text-field-deprecated,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-password-field-deprecated',
    extends: 'sw-text-field-deprecated',
    load: () => import('./src/sw-password-field-deprecated'),
    public: {
        props: [
            'passwordToggleAble',
            'placeholderIsPassword',
            'autocomplete',
            'value',
            'placeholder',
            'copyable',
            'copyableTooltip',
            'idSuffix',
            'ariaLabel',
        ],
        emits: [
            'inheritance-restore',
            'inheritance-remove',
            'update:value',
        ],
        slots: [
            'label',
            'hint',
            'prefix',
            'suffix',
        ],
        blocks: [
            'sw_password_field',
            'sw_text_field',
        ],
        computed: [
            'typeFieldClass',
            'passwordPlaceholder',
            'hasPrefix',
            'hasSuffix',
            'filteredInputAttributes',
        ],
        methods: [
            'onTogglePasswordVisibility',
            'onChange',
            'onInput',
            'restoreInheritance',
            'createInputId',
        ],
    },
});
